<?php


namespace TeamdriveManager\Command\Assign;

use Exception;
use Google_Service_Directory_Group;
use Google_Service_Directory_Member;
use Google_Service_Directory_Members;
use Google_Service_Drive_Permission;
use Google_Service_Drive_TeamDrive;
use Google_Service_Iam_ListServiceAccountsResponse;
use Google_Service_Iam_ServiceAccount;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TeamdriveManager\Service\GoogleDriveService;
use TeamdriveManager\Service\GoogleGroupService;
use TeamdriveManager\Service\GoogleIamService;
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
     * @var GoogleIamService
     */
    private $googleIamService;

    /**
     * @var array
     */
    private $config;

    /**
     * @var User[]
     */
    private $users;

    private $globalGroups = [];

    private $groupCache = [];

    private $memberCache = [];

    private static $iamGroupMail;

    public function __construct(GoogleGroupService $googleGroupService, GoogleDriveService $googleDriveService, GoogleIamService $googleIamService, array $config, array $users)
    {
        parent::__construct();
        $this->googleGroupService = $googleGroupService;
        $this->googleDriveService = $googleDriveService;
        $this->config = $config;
        $this->googleIamService = $googleIamService;
        $this->users = $users;
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $iamConfig = $this->config['iam'];
        if ($iamConfig['enabled'] === true) {
            self::$iamGroupMail = $this->getGroupAddress('serviceaccountsgroup');

            $iamGroupPromise = new Promise(function (callable $resolver, callable $canceler) {
                $this->googleGroupService->getGroupByEmail(self::$iamGroupMail)->then(function (Google_Service_Directory_Group $group) use ($resolver) {
                    $resolver($group);
                }, function (\Exception $exception) use ($resolver) {
                    if ($exception->getCode() === 404) {
                        $this->googleGroupService->createGroup(
                            'Service Account Group',
                            self::$iamGroupMail
                        )->then(function (Google_Service_Directory_Group $group) use ($resolver) {
                            $resolver($group);
                        });
                    }
                });
            });

            $iamGroupPromise->then(function (Google_Service_Directory_Group $group) use ($iamConfig) {
                $this->googleIamService->getServiceAccounts($iamConfig['projectId'])->then(function (Google_Service_Iam_ListServiceAccountsResponse $serviceAccounts) use ($group) {
                    $this->googleGroupService->getMembersForGroup($group)->then(function (Google_Service_Directory_Members $members) use ($serviceAccounts, $group) {
                        $mails = [];
                        /** @var Google_Service_Directory_Member $member */
                        foreach ($members as $member) {
                            $mails[] = $member->getEmail();
                        }

                        /** @var Google_Service_Iam_ServiceAccount $account */
                        foreach ($serviceAccounts->getAccounts() as $account) {
                            if (!in_array($account->getEmail(), $mails, true)) {
                                $this->googleGroupService->addMailToGroup($group, $account->getEmail());
                            }
                        }
                    });
                }, function (Exception $exception) {
                    var_dump($exception->getMessage());
                    echo "An Error Occurred\n";
                });

                $this->globalGroups[] = $group;
            });
        }


        // Request all Teamdrives and filter them
        $this->googleDriveService->getTeamDriveList(function (Google_Service_Drive_TeamDrive $teamDrive) {
            return strpos($teamDrive->getName(), $this->config['teamDriveNameBegin']) === 0;
        })->then(function (array $teamDriveArray) { // Check the Permissions for every Teamdrive with group and user check
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

            $permissionMails = [];
            /** @var $permissions Google_Service_Drive_Permission[] */
            foreach ($permissions as $permission) {
                $permissionMails[strtolower($permission->getEmailAddress())] = $permission;
            }

            $globalGroupMails = [];
            foreach ($this->globalGroups as $group) {
                $globalGroupMails[strtolower($group->getEmail())] = $group;
            }

            foreach ($permissionMails as $permissionMail => $permission) {
                if (array_key_exists($permissionMail, $globalGroupMails)) {
                    $this->checkPermissionForGroup($globalGroupMails[$permissionMail], $teamDrive, $permission, 'organizer');
                    unset($globalGroupMails[$permissionMail]);
                    continue;
                }

                $group = $this->getGroupByEmail($permission->getEmailAddress());
                $this->checkPermissionForGroup($group, $teamDrive, $permission);
                unset($permissionMails[$permissionMail]);
            }

            foreach ($globalGroupMails as $groupMail => $group) {
                $this->checkPermissionForGroup($group, $teamDrive, null, 'organizer');
            }

//            /** @var $permissions Google_Service_Drive_Permission[] */
//            foreach ($permissions as $permission) {
//                /** @var Google_Service_Directory_Group $group */
//                foreach ($this->globalGroups as $group) {
//                    if (strtolower($permission->getEmailAddress()) === strtolower($group->getEmail())) {
//                        $this->checkPermissionForGroup($group, $teamDrive, $permission, 'organizer');
//                        continue 2;
//                    }
//                }
//
//                $group = $this->getGroupByEmail($permission->getEmailAddress());
//                $this->checkPermissionForGroup($group, $teamDrive, $permission);
//            }
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

    private function checkPermissionForGroup(?Google_Service_Directory_Group $group, Google_Service_Drive_TeamDrive $teamDrive, ?Google_Service_Drive_Permission $permission, string $groupRole = null): void
    {
        if ($group !== null && $groupRole === null) {
            $groupRole = $this->getRoleForGroup($group);
        }

        if ($permission === null && $group !== null) {
            $this->googleDriveService->createPermissionForGroup($group, $groupRole, $teamDrive)->then(function () use ($group, $teamDrive) {
                echo 'Created Permission for Group ' . $group->getEmail() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            }, function () use ($group, $teamDrive) {
                echo 'Error while creating Permission for Group ' . $group->getEmail() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            });

            return;
        }

        if ($permission !== null && $group !== null) {
            if ($permission->getRole() !== $groupRole) {
                $this->googleDriveService->updatePermissionForGroup($group, $groupRole, $teamDrive, $permission)->then(function () use ($group, $teamDrive) {
                    echo 'Updated Permission for Group ' . $group->getEmail() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
                }, function () use ($group, $teamDrive) {
                    echo 'Error while updating Permission for Group ' . $group->getEmail() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
                });
            }

            return;
        }

        if ($permission !== null) {
            $this->googleDriveService->deletePermission($teamDrive, $permission)->then(function () use ($teamDrive, $permission) {
                echo 'Deleted Permission for Group ' . $permission->getEmailAddress() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            }, function () use ($permission, $teamDrive) {
                echo 'Error while deleting Permission for Group ' . $permission->getEmailAddress() . ' on TeamDrive ' . $teamDrive->getName() . "\n";
            });
        }
    }

    private function getRoleForGroup(Google_Service_Directory_Group $givenGroup)
    {
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
        // Get all roles
        $roles = array_unique(array_map(function (User $user) {
            return $user->role;
        }, $this->users));

        // For every role
        foreach ($roles as $role) {
            // Load Group for the current Teamdrive and the role
            $this->loadGroupForTeamdriveAndRole($teamDrive, $role)->then(function (Google_Service_Directory_Group $group) {
                // Get Members for the group
                $this->googleGroupService->getMembersForGroup($group)->then(function (Google_Service_Directory_Members $members) use ($group) {
                    $this->memberCache[$group->getId()] = $members;
                });
            });
        }

        /**
         * for every role and group for this teamdrive
         * @var string $role
         * @var Google_Service_Directory_Group $group
         */
        foreach ($this->groupCache[$teamDrive->getName()] as $role => $group) {
            // Go over every User
            foreach ($this->users as $user) {
                // and check the Permission for him
                $this->checkPermissionForTeamdriveUser($teamDrive, $group, $role, $user);
            }

            /** @var Google_Service_Directory_Member $member */
            // For every member in group
            foreach ($this->getMembersForGroup($group) as $member) {
                // if not in the allowed list, remove
                if (!\in_array(strtolower($member->getEmail()), $this->getUserEmails($this->getUsersForTeamdrive($teamDrive)), true)) {
                    $this->googleGroupService->removeMemberFromGroup($group, $member);
                }
            }
        }
    }

    private function getUsersForTeamdrive(Google_Service_Drive_TeamDrive $teamDrive)
    {
        $users = [];

        foreach ($this->users as $user) {
            if (count($user->whitelist) !== 0) {
                if (in_array($teamDrive->getName(), $user->whitelist, true)) {
                    $users[] = $user;
                }
            } else if (!$this->isUserExcludedFromTeamDrive($user, $teamDrive->getName())) {
                $user[] = $user;
            }
        }

        return $users;
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

    private function getGroupByEmail(string $email)
    {
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
        return $this->getGroupAddress(hash('sha256', $teamDrive->getId() . '_' . $role));
    }

    private function getGroupAddress(string $address)
    {
        return $address . '@' . $this->config['domain'];
    }
}
