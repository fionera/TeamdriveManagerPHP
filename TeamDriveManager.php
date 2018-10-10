<?php

class TeamDriveManager
{
    /**
     * @var GoogleRequestQueue
     */
    private $googleRequestQueue;
    /**
     * @var User[]
     */
    private $users;

    /**
     * TeamDriveManager constructor.
     * @param GoogleRequestQueue $googleRequestQueue
     * @param array $users
     */
    public function __construct(GoogleRequestQueue $googleRequestQueue, array $users)
    {
        $this->googleRequestQueue = $googleRequestQueue;
        $this->users = $users;
    }

    public function checkPermissionsForTeamDrive(Google_Service_Drive_TeamDrive $teamDrive): void
    {
        $this->googleRequestQueue->getPermissionArray($teamDrive)->then(function (array $permissions) use ($teamDrive) {
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

    public function checkPermissionForUserAndTeamDrive(?User $user, Google_Service_Drive_TeamDrive $teamDrive, ?Google_Service_Drive_Permission $permission): void
    {
        if ($permission === null && $user !== null) {
            $this->googleRequestQueue->createPermission($user, $teamDrive)->then(function () use ($user, $teamDrive) {
                echo 'Created Permission for User ' . $user->mail . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            }, function () use ($user, $teamDrive) {
                echo 'Error while creating Permission for User ' . $user->mail . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            });

            return;
        }

        if ($permission !== null && $user !== null) {
            if ($permission->getRole() !== $user->role) {
                $this->googleRequestQueue->updatePermission($user, $teamDrive, $permission)->then(function () use ($user, $teamDrive) {
                    echo 'Updated Permission for User ' . $user->mail . ' on TeamDrive ' . $teamDrive->getName() . "\n";
                }, function () use ($user, $teamDrive) {
                    echo 'Error while updating Permission for User ' . $user->mail . ' on TeamDrive ' . $teamDrive->getName() . "\n";
                });
            }

            return;
        }

        if ($permission !== null) {
            $this->googleRequestQueue->deletePermission($teamDrive, $permission)->then(function () use ($teamDrive, $permission) {
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
        return in_array($teamDriveName, $user->excluded, true);
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