<?php declare(strict_types=1);

namespace TeamdriveManager\Service;

use Google_Service_Iam;
use Google_Service_Iam_CreateServiceAccountRequest;
use Google_Service_Iam_ServiceAccount;
use React\Promise\PromiseInterface;

class GoogleIamService
{
    /**
     * @var Google_Service_Iam
     */
    private $iamService;

    /**
     * @var RequestQueue
     */
    private $requestQueue;

    /**
     * GoogleRequestQueue constructor.
     *
     * @param Google_Service_Iam $iamService
     * @param RequestQueue       $requestQueue
     */
    public function __construct(Google_Service_Iam $iamService, RequestQueue $requestQueue)
    {
        $this->iamService = $iamService;
        $this->requestQueue = $requestQueue;
    }

    public function getServiceAccounts(string $projectId): PromiseInterface
    {
        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->iamService->projects_serviceAccounts->listProjectsServiceAccounts('projects/' . $projectId, [
            'pageSize' => 200
        ]);

        return $this->requestQueue->queueRequest($request);
    }

    public function createServiceAccount(string $projectId, string $name): PromiseInterface
    {
        $serviceAccount = new Google_Service_Iam_ServiceAccount();
        $serviceAccount->setDisplayName($name);

        $createServiceAccountRequest = new Google_Service_Iam_CreateServiceAccountRequest();
        $createServiceAccountRequest->setServiceAccount($serviceAccount);
        $createServiceAccountRequest->setAccountId(strtolower(str_replace([' '], '-', $name)));

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->iamService->projects_serviceAccounts->create('projects/' . $projectId, $createServiceAccountRequest);

        return $this->requestQueue->queueRequest($request);
    }

    public function createServiceAccountKey(Google_Service_Iam_ServiceAccount $serviceAccount): PromiseInterface
    {
        $serviceAccountKeyRequest = new \Google_Service_Iam_CreateServiceAccountKeyRequest();

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->iamService->projects_serviceAccounts_keys->create($serviceAccount->getName(), $serviceAccountKeyRequest);

        return $this->requestQueue->queueRequest($request);
    }
}
