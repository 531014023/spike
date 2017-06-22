<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Client\TunnelClient;

use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use Spike\Client\Client;
use Spike\Tunnel\HttpTunnel;
use Spike\Tunnel\TunnelInterface;
use Spike\Parser\SpikeParser;
use Spike\Protocol\Spike;

abstract class TunnelClient implements TunnelClientInterface
{
    /**
     * @var TunnelInterface
     */
    protected $tunnel;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var string
     */
    protected $serverAddress;

    /**
     * The proxy connection
     * @var ConnectionInterface
     */
    protected $proxyConnection;

    /**
     * The local connection
     * @var ConnectionInterface
     */
    protected $localConnection;

    /**
     * @var string
     */
    protected $proxyConnectionId;

    /**
     * @var string
     */
    protected $initBuffer;

    protected $client;

    public function __construct(Client $client, TunnelInterface $tunnel, $proxyConnectionId, $serverAddress, LoopInterface $loop)
    {
        $this->client = $client;
        $this->tunnel = $tunnel;
        $this->proxyConnectionId = $proxyConnectionId;
        $this->serverAddress = $serverAddress;
        $this->loop = $loop;
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $serverConnector = new Connector($this->loop);
        $serverConnector->connect($this->serverAddress)
            ->then([$this, 'handleServerConnection']);
    }

    /**
     * Handles the server connection
     * @param ConnectionInterface $connection
     */
    public function handleServerConnection(ConnectionInterface $connection)
    {
        $this->proxyConnection = $connection;
        $connection->write(new Spike('register_proxy', $this->tunnel->toArray(), [
            'Proxy-Connection-ID' => $this->proxyConnectionId
        ]));

        $parser = new SpikeParser();
        $connection->on('data', function($data) use($parser, $connection){
            $parser->pushIncoming($data);
            $protocol = $parser->parseFirst();
            if ($protocol) {
                echo $protocol;
                $connection->removeAllListeners('data');
                $message = Spike::fromString($protocol);
                if ($message->getAction() == 'start_proxy') {
                    $this->initBuffer = $parser->getRestData();
                    if ($this->tunnel instanceof HttpTunnel) {
                        $localAddress = $this->tunnel->getForwardHost($this->tunnel->getProxyHost());
                    }  else {
                        $localAddress = $this->tunnel->getHost();
                    }
                    $this->createLocalConnector($localAddress);
                }
            }
        });
    }

    protected function createLocalConnector($address)
    {
        $localConnector = new Connector($this->loop);
        $localConnector->connect($address)->then([$this, 'handleLocalConnection']);
    }

    /**
     * {@inheritdoc}
     */
    public function getTunnel()
    {
        return $this->tunnel;
    }
}