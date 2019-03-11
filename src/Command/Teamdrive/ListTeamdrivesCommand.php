<?php declare(strict_types=1);

namespace TeamdriveManager\Command\Teamdrive;

use Google_Service_Drive_Permission;
use Google_Service_Drive_TeamDrive;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TeamdriveManager\Service\GoogleDriveService;

class ListTeamdrivesCommand extends Command
{
    protected static $defaultName = 'td:list';

    /**
     * @var GoogleDriveService
     */
    private $googleDriveService;

    /**
     * @var Google_Service_Drive_TeamDrive[]
     */
    private $teamDrives;

    protected function configure()
    {
        $this->setDescription('List all Teamdrives that you have access to');
    }

    public function __construct(GoogleDriveService $googleDriveServiceService)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveServiceService;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->googleDriveService->getTeamDriveList(function (Google_Service_Drive_TeamDrive $teamDrive) {
            return true; //Return true since we want all Teamdrives
        })->then(function (array $teamDrives) {
            array_map([$this, 'getPermissionsForTeamDrive'], $teamDrives);
        });

        /**
         * @var Google_Service_Drive_TeamDrive
         * @var Google_Service_Drive_Permission[] $permissions
         */
        foreach ($this->teamDrives as $teamDriveName => $permissions) {
            $output->writeln('Permissions for TeamDrive "' . $teamDriveName . '":');

            /** @var Google_Service_Drive_Permission $permission */
            foreach ($permissions as $permission) {
                $output->writeln("\t" . $permission->getRole() . ' - ' . $permission->getEmailAddress());
            }
        }
    }

    private function getPermissionsForTeamDrive(Google_Service_Drive_TeamDrive $teamDrive)
    {
        $this->teamDrives[$teamDrive->getName()] = [];
        $this->googleDriveService->getPermissionArray($teamDrive)->then(function (array $permissions) use ($teamDrive) {
            array_map(function (Google_Service_Drive_Permission $permission) use ($teamDrive) {
                $this->teamDrives[$teamDrive->getName()][] = $permission;
            }, $permissions);
        });
    }
}
