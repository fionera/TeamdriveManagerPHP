<?php declare(strict_types=1);

namespace TeamdriveManager\Service;

use Clue\React\Mq\Queue;
use Exception;
use Google_Client;
use Psr\Http\Message\RequestInterface;
use React\Promise\PromiseInterface;

class RequestQueue
{

    /**
     * @var Queue
     */
    private $queue;

    /**
     * @var Google_Client
     */
    private $client;

    /**
     * GoogleRequestQueue constructor.
     * @param Google_Client $client
     */
    public function __construct(Google_Client $client)
    {
        $this->client = $client;

        $this->queue = new Queue(10, null, function (RequestInterface $request, bool $streamRequest = false) {
            return new \React\Promise\Promise(function (callable $resolve) use ($request, $streamRequest) {

                if ($streamRequest) {
                    $originalClient = $this->client->getHttpClient();

                    $guzzleClient = new \GuzzleHttp\Client([
                        'stream' => true
                    ]);

                    $this->client->setHttpClient($guzzleClient);
                }

                $response = $this->client->execute($request);

                if ($streamRequest) {
                    $this->client->setHttpClient($originalClient);
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
}