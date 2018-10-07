<?php

require_once 'vendor/autoload.php';
$config = include 'config.php';
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $config['serviceAccountFile']);

$users = $config['users'];
$blacklist = $config['blacklist'];

$client = new Google_Client();
$client->useApplicationDefaultCredentials();
$client->setSubject($config['subject']);
$client->setScopes([Google_Service_Drive::DRIVE]);

$driveService = new Google_Service_Drive($client);

$teamDriveList = $driveService->teamdrives->listTeamdrives([
    'pageSize' => 100
]);

/** @var Google_Service_Drive_TeamDrive $teamDrive */
foreach ($teamDriveList as $teamDrive) {
    $name = $teamDrive->getName();
    $id = $teamDrive->getId();
    $currentBlacklist = $blacklist[$name] ?? [];

    if (strpos($name, $config['teamdriveNameBegin']) === 0) {

        $permissions = $driveService->permissions->listPermissions($id, [
            'supportsTeamDrives' => true,
        ]);

        echo 'Getting Permissions for ' . $name . "\n";
        $permissionList = [];
        /** @var Google_Service_Drive_Permission $permission */
        foreach ($permissions as $permission) {
            /** @noinspection PhpParamsInspection */
            $permissionList[] = $driveService->permissions->get($id, $permission->getId(), [
                'supportsTeamDrives' => true,
                'fields' => 'kind,id,emailAddress,domain,role,allowFileDiscovery,displayName,photoLink,expirationTime,teamDrivePermissionDetails,deleted'
            ]);
        }
        /** @noinspection AlterInForeachInspection */
        unset($permission);
        $permissions = $permissionList;
        unset($permissionList);

        foreach ($users as $mail => $role) {
            if (in_array($mail, $currentBlacklist, true)) {
                continue;
            }

            foreach ($permissions as $permission) {
                if ($permission->getEmailAddress() === $mail) {
                    if ($permission->getTeamDrivePermissionDetails()[0]->getRole() !== $role) {
                        echo 'Updating ' . $mail . ' for Teamdrive ' . $name . "\n";

                        $newPermission = new Google_Service_Drive_Permission();
                        $newPermission->setRole($role);

                        $driveService->permissions->update($id, $permission->getId(),
                            $newPermission, [
                                'supportsTeamDrives' => true,
                            ]);
                    }

                    continue 2;
                }
            }

            echo 'Creating ' . $mail . ' for Teamdrive ' . $name . "\n";
            $driveService->permissions->create($id,
                new Google_Service_Drive_Permission([
                    'type' => 'user',
                    'role' => $role,
                    'emailAddress' => $mail
                ]), [
                    'supportsTeamDrives' => true,
                    'sendNotificationEmail' => false,
                ]);
        }

        foreach ($permissions as $permission) {
            $mail = $permission->getEmailAddress();
            if (!array_key_exists($mail, $users) || in_array($mail, $currentBlacklist, true)) {
                echo 'Deleting ' . $mail . ' for Teamdrive ' . $name . "\n";
                $driveService->permissions->delete($id, $permission->getId(), [
                    'supportsTeamDrives' => true,
                ]);
            }
        }
    }
}

