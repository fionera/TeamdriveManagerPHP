<?php

require_once 'vendor/autoload.php';
$config = include 'config.php';
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $config['serviceAccountFile']);

class User
{
    public $mail;
    public $role;
    public $excluded;

    public function __construct(string $mail, string $role, array $excluded)
    {
        $this->mail = $mail;
        $this->role = $role;
        $this->excluded = $excluded;
    }
}

$client = new Google_Client();
$client->useApplicationDefaultCredentials();
$client->setSubject($config['subject']);
$client->setScopes([Google_Service_Drive::DRIVE]);

$driveService = new Google_Service_Drive($client);

$teamDriveList = $driveService->teamdrives->listTeamdrives([
    'pageSize' => 100
]);

/** @var User[] $users */
$users = [];
foreach ($config['users'] as $mail => $role) {

    $blackList = [];
    foreach ($config['blacklist'] as $driveName => $mailList) {
        if (in_array($mail, $mailList, true)) {
            $blackList[] = $driveName;
        }
    }

    $users[] = new User($mail, $role, $blackList);
}
unset($user);

function updatePermission(User $user, Google_Service_Drive $driveService, Google_Service_Drive_TeamDrive $teamDrive, Google_Service_Drive_Permission $permission)
{
    echo 'Updating ' . $user->mail . ' for Teamdrive ' . $teamDrive->getName() . "\n";

    $newPermission = new Google_Service_Drive_Permission();
    $newPermission->setRole($user->role);

    $driveService->permissions->update($teamDrive->getId(), $permission->getId(),
        $newPermission, [
            'supportsTeamDrives' => true,
        ]);
}

function createPermission(User $user, Google_Service_Drive $driveService, Google_Service_Drive_TeamDrive $teamDrive)
{
    echo 'Creating ' . $user->mail . ' for Teamdrive ' . $teamDrive->getName() . "\n";
    $driveService->permissions->create($teamDrive->getId(),
        new Google_Service_Drive_Permission([
            'type' => 'user',
            'role' => $user->role,
            'emailAddress' => $user->mail
        ]), [
            'supportsTeamDrives' => true,
            'sendNotificationEmail' => false,
        ]);
}

function deletePermission(Google_Service_Drive $driveService, Google_Service_Drive_TeamDrive $teamDrive, Google_Service_Drive_Permission $permission)
{
    echo 'Deleting ' . $permission->getEmailAddress() . ' for Teamdrive ' . $teamDrive->getName() . "\n";
    $driveService->permissions->delete($teamDrive->getId(), $permission->getId(), [
        'supportsTeamDrives' => true,
    ]);
}

function setPermissionsForUser(User $user, array $permissions, Google_Service_Drive_TeamDrive $teamDrive, Google_Service_Drive $driveService)
{
    if (!in_array($teamDrive->getName(), $user->excluded, true)) {
        $found = false;
        foreach ($permissions as $permission) {
            if ($permission->getEmailAddress() === $user->mail) {
                if ($permission->getRole() !== $user->role) {
                    updatePermission($user, $driveService, $teamDrive, $permission);
                }

                $found = true;
            }
        }

        if (!$found) {
            createPermission($user, $driveService, $teamDrive);
        }
    } else {
        /** @var Google_Service_Drive_Permission $permission */
        foreach ($permissions as $permission) {
            if ($permission->getEmailAddress() === $user->mail) {
                deletePermission($driveService, $teamDrive, $permission);
            }
        }
    }
}

function getPermissionsForDrive(Google_Service_Drive $driveService, Google_Service_Drive_TeamDrive $teamDrive)
{
    $permissions = $driveService->permissions->listPermissions($teamDrive->getId(), [
        'supportsTeamDrives' => true,
    ]);

    echo 'Getting Permissions for ' . $teamDrive->getName() . "\n";
    $permissionList = [];
    /** @var Google_Service_Drive_Permission $permission */
    foreach ($permissions as $permission) {
        /** @noinspection PhpParamsInspection */
        $permissionList[] = $driveService->permissions->get($teamDrive->getId(), $permission->getId(), [
            'supportsTeamDrives' => true,
            'fields' => 'kind,id,emailAddress,domain,role,allowFileDiscovery,displayName,photoLink,expirationTime,teamDrivePermissionDetails,deleted'
        ]);
    }

    return $permissionList;
}

function deleteOrphanedPermissions(array $permissions, array $users, Google_Service_Drive_TeamDrive $teamDrive, Google_Service_Drive $driveService)
{
    /** @var Google_Service_Drive_Permission $permission */
    foreach ($permissions as $permission) {
        if (!in_array($permission->getEmailAddress(), array_map(function (User $user) {
                return $user->mail;
            }, $users), true) || in_array($teamDrive->getName(), array_map(function (User $user) use ($permission) {
                if ($permission->getEmailAddress() === $user->mail) {
                    return $user->excluded;
                }
                return [];
            }, $users), true)) {
            deletePermission($driveService, $teamDrive, $permission);
        }
    }
}

function createRcloneConfig(Google_Service_Drive_TeamDrive $teamDrive, string $fileName)
{
    $name = getRcloneConfig($teamDrive);

    return <<<EOF
[$name]
type = drive
client_id =
client_secret =
scope = drive
root_folder_id =
service_account_file = $fileName
team_drive = $teamDrive->id


EOF;
}

function getRcloneConfig(Google_Service_Drive_TeamDrive $teamDrive)
{
    return str_replace([' - ', ' / ', ' ', '/', '-'], ['_', '_', '_', '', ''], $teamDrive->getName());
}

function getFilteredTeamdrives(Google_Service_Drive_TeamDriveList $teamdrives, string $teamdriveNameBegin)
{
    $drives = [];

    foreach ($teamdrives as $teamdrive) {
        if (strpos($teamdrive->getName(), $teamdriveNameBegin) === 0) {
            $drives[] = $teamdrive;
        }
    }

    return $drives;
}

if ($argc > 1) {

    if ($argv[1] === 'rclone') {
        if ($argc === 3) {
            $fileName = $argv[2];
        } else {
            $fileName = $config['serviceAccountFile'];
        }

        $rcloneConfigString = '';
        foreach (getFilteredTeamdrives($teamDriveList, $config['teamdriveNameBegin']) as $teamDrive) {
            $rcloneConfigString .= createRcloneConfig($teamDrive, $fileName);
        }

        file_put_contents('rclone.conf', $rcloneConfigString);
    }

    if ($argv[1] === 'create') {
        $teamDrive = new Google_Service_Drive_TeamDrive();
        $teamDrive->setName($argv[2]);
        $requestId = random_int(1, 10000000);
        $teamDrive = $driveService->teamdrives->create($requestId, $teamDrive);
        echo 'Created Teamdrive ' . $teamDrive->getName() . "\n";
    }
} else {
    /** @var Google_Service_Drive_TeamDrive $teamDrive */
    foreach (getFilteredTeamdrives($teamDriveList, $config['teamdriveNameBegin']) as $teamDrive) {
        $permissions = getPermissionsForDrive($driveService, $teamDrive);

        foreach ($users as $user) {
            setPermissionsForUser($user, $permissions, $teamDrive, $driveService);
        }

        deleteOrphanedPermissions($permissions, $users, $teamDrive, $driveService);
    }
}