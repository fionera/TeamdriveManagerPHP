<?php


namespace TeamdriveManager\Command\Assign;

use Exception;
use Google_Service_Drive_Permission;
use Google_Service_Drive_TeamDrive;
use Google_Service_Iam_ListServiceAccountsResponse;
use Google_Service_Iam_ServiceAccount;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TeamdriveManager\Service\GoogleDriveService;
use TeamdriveManager\Service\GoogleIamService;
use TeamdriveManager\Struct\User;

class AssignWithMailCommand extends Command
{
    protected static $defaultName = 'assign:mail';
    /**
     * @var GoogleDriveService
     */
    private $driveService;
    /**
     * @var array
     */
    private $config;
    /**
     * @var array
     */
    private $users;

    public function __construct(GoogleDriveService $driveService, array $config, array $users)
    {
        parent::__construct();
        $this->driveService = $driveService;
        $this->config = $config;
        $this->users = $users;
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->driveService->getFilteredTeamDriveList(function (Google_Service_Drive_TeamDrive $teamDrive) {
            return strpos($teamDrive->getName(), $this->config['teamDriveNameBegin']) === 0;
        })->then(function (array $teamDriveArray) {
            foreach ($teamDriveArray as $teamDrive) {
                $this->checkPermissionsForTeamDrive($teamDrive);
            }
        });
    }


    private function checkPermissionsForTeamDrive(Google_Service_Drive_TeamDrive $teamDrive): void
    {
        $this->driveService->getPermissionArray($teamDrive)->then(function (array $permissions) use ($teamDrive) {
            /** @var $permissions Google_Service_Drive_Permission[] */
            foreach ($permissions as $permission) {
                $user = $this->getUserForEmail($permission->getEmailAddress());
                $this->checkPermissionForUserAndTeamDrive($user, $teamDrive, $permission);
            }

            foreach ($this->getUsersWithoutPermission($permissions, $teamDrive->getName()) as $user) {
                $this->checkPermissionForUserAndTeamDrive($user, $teamDrive, null);
            }
        }, function () {
            echo "An Error Occurred\n";
        });
    }

    private function checkPermissionForUserAndTeamDrive(?User $user, Google_Service_Drive_TeamDrive $teamDrive, ?Google_Service_Drive_Permission $permission): void
    {
        if ($permission === null && $user !== null) {
            $this->driveService->createPermissionForUser($user, $teamDrive)->then(function () use ($user, $teamDrive) {
                echo 'Created Permission for User ' . $user->mail . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            }, function () use ($user, $teamDrive) {
                echo 'Error while creating Permission for User ' . $user->mail . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            });

            return;
        }

        if ($permission !== null && $user !== null) {
            if ($permission->getRole() !== $user->role) {
                $this->driveService->updatePermissionForUser($user, $teamDrive, $permission)->then(function () use ($user, $teamDrive) {
                    echo 'Updated Permission for User ' . $user->mail . ' on TeamDrive ' . $teamDrive->getName() . "\n";
                }, function () use ($user, $teamDrive) {
                    echo 'Error while updating Permission for User ' . $user->mail . ' on TeamDrive ' . $teamDrive->getName() . "\n";
                });
            }

            return;
        }

        if ($permission !== null) {
            $this->driveService->deletePermission($teamDrive, $permission)->then(function () use ($teamDrive, $permission) {
                echo 'Deleted Permission for User ' . $permission->getEmailAddress() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            }, function () use ($permission, $teamDrive) {
                echo 'Error while deleting Permission for User ' . $permission->getEmailAddress() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            });
        }
    }

    /**
     * @param Google_Service_Drive_Permission[] $permissions
     * @param string $teamDriveName
     * @return User[]
     */
    private function getUsersWithoutPermission(array $permissions, string $teamDriveName = ''): array
    {
        $usersWithoutPermission = [];
        foreach ($this->getUserWithTeamDriveAccess($teamDriveName) as $user) {
            foreach ($permissions as $permission) {
                if ($permission->getEmailAddress() === $user->mail) {
                    continue 2;
                }
            }

            $usersWithoutPermission[] = $user;
        }

        return $usersWithoutPermission;
    }

    private function getUserWithTeamDriveAccess(string $teamDriveName): array
    {
        return array_filter($this->users, function (User $user) use ($teamDriveName) {
            return !$this->isUserExcludedFromTeamDrive($user, $teamDriveName);
        });
    }

    private function isUserExcludedFromTeamDrive(User $user, string $teamDriveName): bool
    {
        return \in_array($teamDriveName, $user->excluded, true);
    }

    private function getUserForEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->mail === $email) {
                return $user;
            }
        }

        return null;
    }
}
