<?php declare(strict_types=1);

namespace TeamdriveManager\Command\Iam;

use Exception;
use Google_Service_Iam_ServiceAccount;
use Google_Service_Iam_ServiceAccountKey;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TeamdriveManager\Service\GoogleIamService;

class CreateServiceAccountCommand extends Command
{
    protected static $defaultName = 'iam:serviceaccount:create';

    /**
     * @var array
     */
    private $config;
    /**
     * @var GoogleIamService
     */
    private $iamService;

    public function __construct(array $config, GoogleIamService $iamService)
    {
        parent::__construct();
        $this->config = $config;
        $this->iamService = $iamService;
    }

    protected function configure()
    {
        $this
            ->addOption('name', '-N', InputOption::VALUE_REQUIRED, 'The name for the Service Account')
            ->addOption('fileName', '-f', InputOption::VALUE_REQUIRED, 'The filename for the Key')
            ->addOption('amount', '-a', InputOption::VALUE_OPTIONAL, 'The amount of accounts to create', 1)
            ->addOption('projectId', '-p', InputOption::VALUE_OPTIONAL, 'The Project ID')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $iam = $this->config['iam'];

        if ($iam['enabled'] !== true) {
            $output->writeln('IAM is disabled. Please enable it for use.');

            return;
        }

        $inputName = $input->getOption('name');
        if ($inputName === null) {
            $inputName = $io->ask('Serviceaccount Name');
        }

        $amount = $input->getOption('amount');

        if (is_array($iam['projectId'])) {
            $iam['projectId'] = $iam['projectId'][0];
        }

        if ($input->getOption('projectId') !== null) {
            $iam['projectId'] = $input->getOption('projectId');
        }

        for ($i = 1; $i < $amount + 1; ++$i) {
            $name = str_replace('INDEX', $i, $inputName);

            $this->iamService->createServiceAccount($iam['projectId'], $name)->then(function (Google_Service_Iam_ServiceAccount $serviceAccount) use ($output, $input, $i) {
                $output->writeln('Successfully created, creating Key');

                $this->iamService->createServiceAccountKey($serviceAccount)->then(function (Google_Service_Iam_ServiceAccountKey $serviceAccountKey) use ($output, $input, $serviceAccount, $i) {
                    $fileName = $input->getOption('fileName');

                    if ($fileName === null) {
                        $fileName = $serviceAccount->getProjectId() . '-' . strtolower(str_replace(' ', '-', $serviceAccount->displayName)) . '.json';
                    }

                    if (!is_dir('Files') && !mkdir('Files') && !is_dir('Files')) {
                        throw new \RuntimeException(sprintf('Directory "%s" was not created', 'Files'));
                    }

                    file_put_contents('Files/' . $fileName, base64_decode($serviceAccountKey->getPrivateKeyData()));

                    $output->writeln('Successfully created, saved to ' . $fileName);
                }, function (Exception $exception) {
                    var_dump($exception->getMessage());
                    echo "An Error Occurred\n";
                });
            }, function (Exception $exception) {
                var_dump($exception->getMessage());
                echo "An Error Occurred\n";
            });
        }
    }
}
