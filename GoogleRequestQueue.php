<?php declare(strict_types=1);

use Clue\React\Mq\Queue;
use Psr\Http\Message\RequestInterface;
use React\Promise\PromiseInterface;

class GoogleRequestQueue
{

    /**
     * @var Queue
     */
    private $queue;
    /**
     * @var Google_Service_Drive
     */
    private $driveService;

    /**
     * GoogleRequestQueue constructor.
     * @param Google_Service_Drive $drive_service
     */
    public function __construct(Google_Service_Drive $drive_service)
    {
        $this->driveService = $drive_service;

        $this->queue = new Queue(10, null, function (RequestInterface $request, bool $streamRequest = false) {
            return new \React\Promise\Promise(function (callable $resolve) use ($request, $streamRequest) {

                if ($streamRequest) {
                    $originalClient = $this->driveService->getClient()->getHttpClient();

                    $guzzleClient = new \GuzzleHttp\Client([
                        'stream' => true
                    ]);

                    $this->driveService->getClient()->setHttpClient($guzzleClient);
                }

                $response = $this->driveService->getClient()->execute($request);

                if ($streamRequest) {
                    $this->driveService->getClient()->setHttpClient($originalClient);
                }

                if ($response instanceof Exception) {
                    var_dump($response);
                }

                $resolve($response);
            });
        });
    }

    public function queueRequest(RequestInterface $request): PromiseInterface
    {
        $queue = $this->queue;
        return $queue($request);
    }

    public function queueStreamRequest(RequestInterface $request): PromiseInterface
    {
        $queue = $this->queue;
        return $queue($request, true);
    }

    public function updatePermission(User $user, Google_Service_Drive_TeamDrive $teamDrive, Google_Service_Drive_Permission $permission): PromiseInterface
    {
        echo 'Updating ' . $user->mail . ' for Teamdrive ' . $teamDrive->getName() . "\n";

        $newPermission = new Google_Service_Drive_Permission();
        $newPermission->setRole($user->role);

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->permissions->update($teamDrive->getId(), $permission->getId(),
            $newPermission, [
                'supportsTeamDrives' => true,
            ]);

        return $this->queueRequest($request);
    }

    public function createPermission(User $user, Google_Service_Drive_TeamDrive $teamDrive): PromiseInterface
    {
        echo 'Creating ' . $user->mail . ' for Teamdrive ' . $teamDrive->getName() . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->permissions->create($teamDrive->getId(),
            new Google_Service_Drive_Permission([
                'type' => 'user',
                'role' => $user->role,
                'emailAddress' => $user->mail
            ]), [
                'supportsTeamDrives' => true,
                'sendNotificationEmail' => false,
            ]);

        return $this->queueRequest($request);
    }

    public function deletePermission(Google_Service_Drive_TeamDrive $teamDrive, Google_Service_Drive_Permission $permission): PromiseInterface
    {
        echo 'Deleting ' . $permission->getEmailAddress() . ' for Teamdrive ' . $teamDrive->getName() . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->permissions->delete($teamDrive->getId(), $permission->getId(), [
            'supportsTeamDrives' => true,
        ]);

        return $this->queueRequest($request);

    }

    public function getPermission(Google_Service_Drive_TeamDrive $teamDrive, string $id): PromiseInterface
    {
        echo 'Getting Permission ' . $id . ' for Teamdrive ' . $teamDrive->getName() . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->permissions->get($teamDrive->getId(), $id, [
            'supportsTeamDrives' => true,
            'fields' => 'kind,id,emailAddress,domain,role,allowFileDiscovery,displayName,photoLink,expirationTime,teamDrivePermissionDetails,deleted'
        ]);

        return $this->queueRequest($request);
    }

    public function getPermissionList(Google_Service_Drive_TeamDrive $teamDrive): PromiseInterface
    {
        echo 'Getting PermissionList for ' . $teamDrive->getName() . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->permissions->listPermissions($teamDrive->getId(), [
            'supportsTeamDrives' => true,
        ]);

        return $this->queueRequest($request);
    }

    public function getPermissionArray(Google_Service_Drive_TeamDrive $teamDrive): PromiseInterface
    {
        echo 'Getting Permissions for ' . $teamDrive->getName() . "\n";

        return $this->getPermissionList($teamDrive)->then(function (Google_Service_Drive_PermissionList $permissionList) use ($teamDrive) {
            $permissionRequestPromises = [];

            /** @var Google_Service_Drive_Permission $permission */
            foreach ($permissionList as $permission) {
                $permissionRequestPromises[] = $this->getPermission($teamDrive, $permission->getId());
            }

            return \GuzzleHttp\Promise\all($permissionRequestPromises);
        });
    }

    public function getTeamDriveList(callable $filter, int $pageSize = 100): PromiseInterface
    {
        echo 'Getting TeamDrive List' . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->teamdrives->listTeamdrives([
            'pageSize' => $pageSize
        ]);

        return $this->queueRequest($request)->then(function (Google_Service_Drive_TeamDriveList $teamDriveList) use ($filter) {
            $filteredDriveList = [];
            foreach ($teamDriveList as $teamDrive) {
                if ($filter($teamDrive) === true) {
                    $filteredDriveList[] = $teamDrive;
                }
            }

            return $filteredDriveList;
        });
    }

    public function createTeamDrive(string $teamDriveName): PromiseInterface
    {
        echo 'Creating Teamdrive ' . $teamDriveName . "\n";

        $teamDrive = new Google_Service_Drive_TeamDrive();
        $teamDrive->setName($teamDriveName);
        try {
            $requestId = random_int(1, 10000000);
        } catch (Exception $e) {
            echo 'Cant get random int for id';
            die();
        }

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->teamdrives->create($requestId, $teamDrive);

        return $this->queueRequest($request);
    }


    public function getFileInformation(string $fileID): PromiseInterface
    {
        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->files->get($fileID, [
            'supportsTeamDrives' => true
        ]);

        return $this->queueRequest($request);
    }

    public function downloadFile(string $fileID): PromiseInterface
    {
        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->files->get($fileID, [
            'supportsTeamDrives' => true,
            'alt' => 'media',
        ]);

        return $this->queueStreamRequest($request);
    }

    public function createFileCopy(string $fileID): PromiseInterface
    {
        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->files->copy($fileID,
            new Google_Service_Drive_DriveFile(),
            [
                'supportsTeamDrives' => true,
            ]);

        return $this->queueRequest($request);
    }

    public function deleteFile(string $fileID): PromiseInterface
    {
        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->files->delete($fileID,
            [
                'supportsTeamDrives' => true,
            ]);

        return $this->queueRequest($request);
    }
}