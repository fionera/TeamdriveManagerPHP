<?php


namespace TeamdriveManager\Command\Teamdrive;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TeamdriveManager\Service\GoogleDriveService;

class CreateTeamdriveCommand extends Command
{
    protected static $defaultName = 'td:create';

    /**
     * @var GoogleDriveService
     */
    private $googleDriveService;
    /**
     * @var array
     */
    private $config;

    public function __construct(GoogleDriveService $googleDriveServiceService, array $config)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveServiceService;
        $this->config = $config;
    }


    protected function configure()
    {
        $this
            ->addOption('name', '-N', InputOption::VALUE_REQUIRED, 'The name for the Teamdrive')
            ->addOption('prefix', '-p', InputOption::VALUE_NONE, 'Should the prefix be added');
    }


    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getOption('name');
        if ($name === null) {
            $name = $io->ask('Teamdrive Name');
        }

        if ($input->getOption('prefix')) {
            $name = $this->config['teamdriveNameBegin'] . $name;
        }

        $this->googleDriveService->createTeamDrive($name)->then(function () use ($name) {
        });
    }
}
