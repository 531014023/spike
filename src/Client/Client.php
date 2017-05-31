<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Client;

use React\EventLoop\LoopInterface;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use Spike\Protocol\DomainRegisterRequest;
use Spike\Protocol\Factory;
use Spike\Protocol\MessageInterface;
use Spike\Protocol\ProxyRequest;
use GuzzleHttp\Client as HttpClient;
use Spike\Protocol\ProxyResponse;

class Client
{
    protected $proxyHosts = [
        'foo.domain.com' => '127.0.0.1:8080'
    ];

    /**
     * @var Connector
     */
    protected $connector;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var string
     */
    protected $serverAddress;

    protected $loop;

    public function __construct($server, LoopInterface $loop = null, HttpClient $client = null)
    {
        $this->serverAddress = $server;
        if (is_null($client)) {
            $client = new HttpClient();
        }
        $this->httpClient = $client;
        if (is_null($loop)) {
            $loop = LoopFactory::create();
        }
        $this->loop = $loop;
        $this->connector = new Connector($loop);
    }

    public function run()
    {
        $this->connector->connect($this->serverAddress)->then(function(ConnectionInterface $connection){
            $this->uploadProxyHosts($connection); //Reports the proxy hosts
            $connection->on('data', function($data) use ($connection){
                $protocol = Factory::create($data);
                if ($protocol === false) {
                    $connection->close();
                }
                $this->acceptConnection($connection, $protocol);
            });
        });
        $this->loop->run();
    }

    protected function acceptConnection(ConnectionInterface $connection, MessageInterface $protocol)
    {
       if ($protocol instanceof ProxyRequest) {
            $request = $protocol->getRequest();
            $response = $this->httpClient->send($request);
            $connection->write(new ProxyResponse($response));
        }
    }

    protected function uploadProxyHosts(ConnectionInterface $connection)
    {
        $connection->write(new DomainRegisterRequest(array_keys($this->proxyHosts)));
    }
}