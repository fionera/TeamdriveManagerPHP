<?php


namespace TeamdriveManager\Command\Assign;


use Google_Service_Directory_Group;
use Google_Service_Directory_Member;
use Google_Service_Directory_Members;
use Google_Service_Drive_Permission;
use Google_Service_Drive_TeamDrive;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TeamdriveManager\Service\GoogleDriveService;
use TeamdriveManager\Service\GoogleGroupService;
use TeamdriveManager\Struct\User;

class AssignWithGroupCommand extends Command
{
    protected static $defaultName = 'assign:group';

    /**
     * @var GoogleGroupService
     */
    private $googleGroupService;

    /**
     * @var GoogleDriveService
     */
    private $googleDriveService;

    /**
     * @var array
     */
    private $config;

    /**
     * @var User[]
     */
    private $users;

    private $groupCache = [];

    private $memberCache = [];

    public function __construct(GoogleGroupService $googleGroupService, GoogleDriveService $googleDriveService, array $config, array $users)
    {
        parent::__construct();
        $this->googleGroupService = $googleGroupService;
        $this->googleDriveService = $googleDriveService;
        $this->config = $config;
        $this->users = $users;
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->googleDriveService->getTeamDriveList(function (Google_Service_Drive_TeamDrive $teamDrive) {
            return strpos($teamDrive->getName(), $this->config['teamDriveNameBegin']) === 0;
        })->then(function (array $teamDriveArray) {
            array_map([$this, 'checkPermissionsForTeamdrive'], $teamDriveArray);
        });

        $this->googleDriveService->getTeamDriveList(function (Google_Service_Drive_TeamDrive $teamDrive) {
            return strpos($teamDrive->getName(), $this->config['teamDriveNameBegin']) === 0;
        })->then(function (array $teamDriveArray) {
            foreach ($teamDriveArray as $teamDrive) {
                $this->checkGroupPermissionsForTeamdrive($teamDrive);
            }
        });
    }

    private function checkGroupPermissionsForTeamdrive(Google_Service_Drive_TeamDrive $teamDrive): void
    {
        $this->googleDriveService->getPermissionArray($teamDrive)->then(function (array $permissions) use ($teamDrive) {
            foreach ($this->getGroupsWithoutPermission($permissions, $teamDrive) as $group) {
                $this->checkPermissionForGroup($group, $teamDrive, null);
            }

            /** @var $permissions Google_Service_Drive_Permission[] */
            foreach ($permissions as $permission) {
                $group = $this->getGroupByEmail($permission->getEmailAddress());
                $this->checkPermissionForGroup($group, $teamDrive, $permission);
            }
        }, function () {
            echo "An Error Occurred\n";
        });
    }

    /**
     * @param Google_Service_Drive_Permission[] $permissions
     * @param Google_Service_Drive_TeamDrive $teamDrive
     * @return User[]
     */
    private function getGroupsWithoutPermission(array $permissions, Google_Service_Drive_TeamDrive $teamDrive): array
    {
        $groupsWithoutPermission = [];
        /** @var Google_Service_Directory_Group $group */
        foreach ($this->groupCache[$teamDrive->getName()] as $group) {
            foreach ($permissions as $permission) {
                if ($permission->getEmailAddress() === $group->getEmail()) {
                    continue 2;
                }
            }

            $groupsWithoutPermission[] = $group;
        }

        return $groupsWithoutPermission;
    }

    private function checkPermissionForGroup(?Google_Service_Directory_Group $group, Google_Service_Drive_TeamDrive $teamDrive, ?Google_Service_Drive_Permission $permission): void
    {
        $groupRole = null;
        if ($group !== null){
            $groupRole = $this->getRoleForGroup($group);
        }

        if ($permission === null && $group !== null) {
            $this->googleDriveService->createPermissionForGroup($group, $groupRole, $teamDrive)->then(function () use ($group, $teamDrive) {
                echo 'Created Permission for Group ' . $group->getEmail() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            }, function () use ($group, $teamDrive) {
                echo 'Error while creating Permission for User ' . $group->getEmail() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            });

            return;
        }

        if ($permission !== null && $group !== null) {
            if ($permission->getRole() !== $groupRole) {
                $this->googleDriveService->updatePermissionForGroup($group, $groupRole, $teamDrive, $permission)->then(function () use ($group, $teamDrive) {
                    echo 'Updated Permission for User ' . $group->getEmail() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
                }, function () use ($group, $teamDrive) {
                    echo 'Error while updating Permission for User ' . $group->getEmail() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
                });
            }

            return;
        }

        if ($permission !== null) {
            $this->googleDriveService->deletePermission($teamDrive, $permission)->then(function () use ($teamDrive, $permission) {
                echo 'Deleted Permission for User ' . $permission->getEmailAddress() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            }, function () use ($permission, $teamDrive) {
                echo 'Error while deleting Permission for User ' . $permission->getEmailAddress() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            });
        }
    }

    private function getRoleForGroup(Google_Service_Directory_Group $givenGroup) {
        foreach ($this->groupCache as $teamdriveId => $groups) {
            /**
             * @var string $role
             * @var Google_Service_Directory_Group $group
             */
            foreach ($groups as $role => $group) {
                if ($this->isMailEquals($givenGroup->getId(), $group->getId())) {
                    return $role;
                }
            }
        }

        return null;
    }


    private function checkPermissionsForTeamdrive(Google_Service_Drive_TeamDrive $teamDrive): void
    {
        $roles = array_unique(array_map(function (User $user) {
            return $user->role;
        }, $this->users));

        foreach ($roles as $role) {
            $this->loadGroupForTeamdriveAndRole($teamDrive, $role)->then(function (Google_Service_Directory_Group $group) {
                $this->googleGroupService->getMembersForGroup($group)->then(function (Google_Service_Directory_Members $members) use ($group) {
                    $this->memberCache[$group->getId()] = $members;
                });
            });
        }

        /**
         * @var string $role
         * @var Google_Service_Directory_Group $group
         */
        foreach ($this->groupCache[$teamDrive->getName()] as $role => $group) {
            foreach ($this->users as $user) {
                $this->checkPermissionForTeamdriveUser($teamDrive, $group, $role, $user);
            }

            /** @var Google_Service_Directory_Member $member */
            foreach ($this->getMembersForGroup($group) as $member) {
                if (!\in_array(strtolower($member->getEmail()), $this->getUserEmails($this->getUsersForTeamdrive($teamDrive)), true)) {
                    $this->googleGroupService->removeMemberFromGroup($group, $member);
                }
            }
        }
    }

    private function getUsersForTeamdrive(Google_Service_Drive_TeamDrive $teamDrive)
    {
        return array_filter($this->users, function (User $user) use ($teamDrive) {
            return !$this->isUserExcludedFromTeamDrive($user, $teamDrive->getName());
        });
    }

    private function isUserExcludedFromTeamDrive(User $user, string $teamDriveName): bool
    {
        return \in_array($teamDriveName, $user->excluded, true);
    }

    private function getUserEmails(array $users = null)
    {
        return array_map(function (User $user) {
            return strtolower($user->mail);
        }, $users ?? $this->users);
    }

    private function getGroupByEmail(string $email){
        foreach ($this->groupCache as $teamdriveId => $groups) {
            /**
             * @var string $role
             * @var Google_Service_Directory_Group $group
             */
            foreach ($groups as $role => $group) {
                if ($this->isMailEquals($email, $group->getEmail())) {
                    return $group;
                }
            }
        }

        return null;
    }

    private function getMembersForGroup(Google_Service_Directory_Group $group)
    {
        return $this->memberCache[$group->getId()] ?? [];
    }

    private function checkPermissionForTeamdriveUser(Google_Service_Drive_TeamDrive $teamDrive, Google_Service_Directory_Group $group, string $groupRole, User $user)
    {
        /** @var Google_Service_Directory_Member $member */
        foreach ($this->getMembersForGroup($group) as $member) {
            /** @noinspection NotOptimalIfConditionsInspection */
            if ($this->isMailEquals($member->getEmail(), $user->mail) && $groupRole !== $user->role) {
                $this->googleGroupService->removeMemberFromGroup($group, $member);
                break;
            }

            if ($groupRole === $user->role && $this->isMailEquals($member->getEmail(), $user->mail)) {
                return;
            }
        }
        unset($member);

        if ($groupRole === $user->role && !$this->isUserExcludedFromTeamDrive($user, $teamDrive->getName())) {
            $this->googleGroupService->addMailToGroup($group, $user->mail);
        }
    }

    private function loadGroupForTeamdriveAndRole(Google_Service_Drive_TeamDrive $teamDrive, string $role): PromiseInterface
    {
        return new Promise(function (callable $resolver, callable $canceler) use ($teamDrive, $role) {
            if (isset($this->groupCache[$teamDrive->getName()][$role])) {
                $resolver($this->groupCache[$teamDrive->getName()][$role]);
                return;
            }

            $this->googleGroupService->getGroupByEmail($this->getGroupAddressForTeamdrive($teamDrive, $role))->then(function (Google_Service_Directory_Group $group) use ($teamDrive, $role, $resolver) {
                $this->groupCache[$teamDrive->getName()][$role] = $group;

                $resolver($group);

            }, function (\Exception $exception) use ($teamDrive, $role, $resolver) {
                if ($exception->getCode() === 404) {
                    $this->googleGroupService->createGroup(
                        $this->getGroupNameForTeamdrive($teamDrive, $role),
                        $this->getGroupAddressForTeamdrive($teamDrive, $role)
                    )->then(function (Google_Service_Directory_Group $group) use ($teamDrive, $role, $resolver) {
                        $this->groupCache[$teamDrive->getName()][$role] = $group;

                        $resolver($group);
                    });
                }
            });
        });
    }

    private function isMailEquals(string $mailA, string $mailB): bool
    {
        return strtolower($mailA) === strtolower($mailB);
    }

    private function getGroupNameForTeamdrive(Google_Service_Drive_TeamDrive $teamDrive, string $role)
    {
        return ucfirst($role) . ' | ' . $teamDrive->getName();
    }

    private function getGroupAddressForTeamdrive(Google_Service_Drive_TeamDrive $teamDrive, string $role)
    {
        return hash('sha256', $teamDrive->getId() . '_' . $role) . '@fionera.de';
    }
}