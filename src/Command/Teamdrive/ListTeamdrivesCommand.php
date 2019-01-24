<?php


namespace TeamdriveManager\Command\Teamdrive;

use Google_Service_Drive_Permission;
use Google_Service_Drive_PermissionList;
use Google_Service_Drive_TeamDrive;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TeamdriveManager\Service\GoogleDriveService;

class ListTeamdrivesCommand extends Command
{
    protected static $defaultName = 'td:list';

    /**
     * @var GoogleDriveService
     */
    private $googleDriveService;

    /**
     * @var array
     */
    private $config;

    /**
     * @var Google_Service_Drive_TeamDrive[]
     */
    private $teamDrives;

    /**
     * @var Google_Service_Drive_Permission[]
     */
    private $permissionList;

    public function __construct(GoogleDriveService $googleDriveServiceService, array $config)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveServiceService;
        $this->config = $config;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {

        $this->googleDriveService->getTeamDriveList()->done(function ($result) {

            /** @var Google_Service_Drive_TeamDrive[] $teamdrives */
            $this->teamDrives = $result['teamDrives'];

        });

        /** @var Google_Service_Drive_TeamDrive $teamdrive */
        foreach ($this->teamDrives as $teamdrive) {
            $this->googleDriveService->getPermissionArray($teamdrive)
                ->done(function ($result) use ($teamdrive) {
                   $this->permissionList[$teamdrive->getName()] = $result;
                });
        }

        /** @var Google_Service_Drive_Permission[] $permission */
        foreach ($this->permissionList as $name => $permissions) {

            echo 'Permissions for TeamDrive "' . $name . '":' . PHP_EOL;

            /** @var Google_Service_Drive_Permission $permission */
            foreach ($permissions as $permission) {
                echo "\t" . $permission->getRole() . ' - ' . $permission->getEmailAddress() . PHP_EOL;
            }

        }

    }
}