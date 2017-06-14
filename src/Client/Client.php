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
use Slince\Event\Dispatcher;
use Slince\Event\Event;
use Spike\Buffer\BufferInterface;
use Spike\Client\Handler\HandlerInterface;
use Spike\Client\Handler\ProxyRequestHandler;
use Spike\Client\Tunnel\HttpTunnel;
use Spike\Client\Tunnel\TcpTunnel;
use Spike\Client\Tunnel\TunnelFactory;
use Spike\Client\Tunnel\TunnelInterface;
use Spike\Client\TunnelClient\TcpTunnelClient;
use Spike\Exception\InvalidArgumentException;
use Spike\Protocol\MessageInterface;
use Spike\Buffer\SpikeBuffer;
use Spike\Protocol\Spike;

class Client
{
    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var Connector
     */
    protected $connector;

    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * Array of tunnel connections
     * @var ConnectionInterface[]
     */
    protected $tunnelConnections;
    /**
     * @var string
     */
    protected $serverAddress;

    /**
     * @var array
     */
    protected $credential;

    /**
     * @var string
     */
    protected $id;

    /**
     * Tunnels
     * @var TunnelInterface
     */
    protected $tunnels = [];

    public function __construct($serverAddress, $tunnels, LoopInterface $loop = null, Dispatcher $dispatcher = null)
    {
        $this->serverAddress = $serverAddress;
        $this->dispatcher = $dispatcher ?: new Dispatcher();
        $this->loop = $loop ?: LoopFactory::create();
        $this->connector = new Connector($this->loop);
        $this->tunnels = $this->createTunnels($tunnels);
    }

    /**
     * Checks whether the client is authorized
     * @return bool
     */
    public function isAuthorized()
    {
        return !empty($this->id);
    }

    /**
     * @param array $data
     * @return TunnelInterface[]
     */
    protected function createTunnels($data)
    {
        $tunnels = [];
        foreach ($data as $info) {
            $tunnel = TunnelFactory::fromArray($info);
            $tunnel->setControlConnection($this->connection);
            $tunnels[] = $tunnel;
        }
        return $tunnels;
    }

    public function run()
    {
        $this->connector->connect($this->serverAddress)->then(function(ConnectionInterface $connection){
            //Emit the event
            $this->dispatcher->dispatch(new Event(EventStore::CONNECT_TO_SERVER, $this, [
                'connection' => $connection
            ]));
            $this->connection = $connection;
            $this->requestAuth($connection);
            $this->handleConnection($connection);
        });
        $this->dispatcher->dispatch(EventStore::CLIENT_RUN);
        $this->loop->run();
    }

    protected function handleConnection(ConnectionInterface $connection)
    {
        try {
            $buffer = new SpikeBuffer($connection);
            $buffer->gather(function(BufferInterface $buffer) use ($connection){
                $message = Spike::fromString($buffer);
                $this->dispatcher->dispatch(new Event(EventStore::RECEIVE_MESSAGE, $this, [
                    'message' => $message,
                    'connection' => $connection
                ]));
                $this->createMessageHandler($message)->handle($message);
                $buffer->flush(); //Flush the buffer and continue gather message
            });
        } catch (InvalidArgumentException $exception) {
            $this->dispatcher->dispatch(new Event(EventStore::CONNECTION_ERROR, $this, [
                'connection' => $connection,
                'exception' => $exception,
            ]));
        }
    }

    protected function requestAuth(ConnectionInterface $connection)
    {
        $authInfo = [
            'os' => PHP_OS,
            'username' => '',
            'password' => '',
            'version' => '',
        ];
        $connection->write(new Spike('auth', $authInfo));
    }

    public function createTunnelConnection(TunnelInterface $tunnel)
    {
        $connector = new Connector($this->loop);
        $connector->connect($this->serverAddress)->then(function(ConnectionInterface $connection) use ($tunnel){
            $this->tunnelConnections[] = $connection;
            $tunnel->setConnection($connection); //sets tunnel connection for the tunnel
            $connection->write(new Spike('register_proxy', $tunnel->toArray()));
            $this->handleConnection($connection);
        });
    }

    public function createTunnelClient(TunnelInterface $tunnel, $localAddress)
    {
        $tunnelClient = new TcpTunnelClient($tunnel, $localAddress, $this->loop);
        $tunnelClient->run();
    }

    /**
     * @param string $id
     */
    public function setClientId($id)
    {
        $this->id = $id;
    }

    /**
     * @return TunnelInterface[]
     */
    public function getTunnels()
    {
        return $this->tunnels;
    }

    /**
     * @return Dispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Finds the matching tunnel
     * @param array $tunnelInfo
     * @return bool|TunnelInterface
     */
    public function findTunnel($tunnelInfo)
    {
        foreach ($this->getTunnels() as $tunnel) {
            $matching = $tunnel->getRemotePort() == $tunnelInfo['port'];
            $matching = $matching && (
                $tunnel instanceof TcpTunnel
                || $tunnel->supportProxyHost($tunnelInfo['proxyHost'])
            );
            if ($matching) {
                return $tunnel;
            }
        }
        return false;
    }

    /**
     * Creates the handler for the received message
     * @param MessageInterface $message
     * @return HandlerInterface
     */
    protected function createMessageHandler($message)
    {
        if ($message instanceof StartProxy) {
            $handler = new ProxyRequestHandler($this, $this->connection);
        } else {
            throw new InvalidArgumentException(sprintf('Cannot find handler for message type: "%s"',
                get_class($message)
            ));
        }
        return $handler;
    }
}