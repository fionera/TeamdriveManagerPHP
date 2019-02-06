<?php declare(strict_types=1);

namespace TeamdriveManager\Command\Iam;

use Exception;
use Google_Service_Iam_ListServiceAccountsResponse;
use Google_Service_Iam_ServiceAccount;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TeamdriveManager\Service\GoogleIamService;

class ListServiceAccountsCommand extends Command
{
    protected static $defaultName = 'iam:serviceaccount:list';

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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $iam = $this->config['iam'];

        if ($iam['enabled'] !== true) {
            $output->writeln('IAM is disabled. Please enable it for use.');

            return;
        }

        if (!is_array($iam['projectId'])) {
            $iam['projectId'] = [$iam['projectId']];
        }

        foreach ($iam['projectId'] as $projectId) {
            $this->iamService->getServiceAccounts($projectId)->then(function (Google_Service_Iam_ListServiceAccountsResponse $serviceAccounts) use ($output) {
                /** @var Google_Service_Iam_ServiceAccount $account */
                foreach ($serviceAccounts->getAccounts() as $account) {
                    $output->writeln($account->getDisplayName());
                }
            }, function (Exception $exception) {
                var_dump($exception->getMessage());
                echo "An Error Occurred\n";
            });
        }


    }
}
