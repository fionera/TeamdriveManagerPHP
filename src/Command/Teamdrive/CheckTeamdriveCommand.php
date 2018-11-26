<?php


namespace TeamdriveManager\Command\Teamdrive;


use Google_Service_Drive_TeamDrive;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TeamdriveManager\Service\GoogleDriveService;
use TeamdriveManager\Service\TeamDriveService;

class CheckTeamdriveCommand extends Command
{
    protected static $defaultName = 'td:check';

    /**
     * @var GoogleDriveService
     */
    private $googleDriveService;
    /**
     * @var TeamDriveService
     */
    private $teamDriveService;
    /**
     * @var array
     */
    private $config;

    public function __construct(GoogleDriveService $googleDriveService, TeamDriveService $teamDriveService, array $config)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveService;
        $this->teamDriveService = $teamDriveService;
        $this->config = $config;
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->googleDriveService->getTeamDriveList(function (Google_Service_Drive_TeamDrive $teamDrive) {
            return strpos($teamDrive->getName(), $this->config['teamDriveNameBegin']) === 0;
        })->then(function (array $teamDriveArray) {
            foreach ($teamDriveArray as $teamDrive) {
                $this->teamDriveService->checkPermissionsForTeamDrive($teamDrive);
            }
        });
    }
}