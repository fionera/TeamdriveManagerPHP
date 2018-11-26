<?php


namespace TeamdriveManager\Command\Teamdrive;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TeamdriveManager\Service\GoogleDriveService;
use TeamdriveManager\Service\TeamDriveService;

class CreateTeamdriveCommand extends Command
{
    protected static $defaultName = 'td:create';

    /**
     * @var GoogleDriveService
     */
    private $googleDriveService;
    /**
     * @var TeamDriveService
     */
    private $teamDriveService;

    public function __construct(GoogleDriveService $googleDriveService, TeamDriveService $teamDriveService)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveService;
        $this->teamDriveService = $teamDriveService;
    }


    protected function configure()
    {
        $this
            ->addOption('name', '-n', InputOption::VALUE_REQUIRED, 'The name for the Teamdrive');
    }


    public function run(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $name = $io->ask('Teamdrive Name');

        $this->googleDriveService->createTeamDrive($name)->then(function () use ($name) {
            $this->googleDriveService->getTeamDriveList(function ($teamDriveName) use ($name) {
                return $teamDriveName === $name;
            })->then(function (array $teamDriveArray) {
                foreach ($teamDriveArray as $teamDrive) {
                    $this->teamDriveService->checkPermissionsForTeamDrive($teamDrive);
                }
            });
        });
    }
}