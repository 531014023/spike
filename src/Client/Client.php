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
use Spike\Client\Timer\Heartbeat;
use Spike\Logger\Logger;
use Spike\Timer\MemoryWatcher;
use Spike\Timer\TimerInterface;
use Spike\Timer\UseTimerTrait;
use Spike\Tunnel\TunnelFactory;
use Spike\Tunnel\TunnelInterface;
use Spike\Client\TunnelClient\TcpTunnelClient;
use Spike\Client\TunnelClient\TunnelClient;
use Spike\Client\TunnelClient\TunnelClientInterface;
use Spike\Exception\InvalidArgumentException;
use Spike\Exception\RuntimeException;
use Spike\Parser\SpikeParser;
use Spike\Protocol\Spike;
use Spike\Protocol\SpikeInterface;

class Client
{
    use UseTimerTrait;

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
    protected $controlConnection;

    /**
     * @var TunnelClientCollection
     */
    protected $tunnelClients;

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

    /**
     * Auth info
     * @var array
     */
    protected $auth;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct($serverAddress, $tunnels, $auth, LoopInterface $loop = null, Dispatcher $dispatcher = null)
    {
        $this->serverAddress = $serverAddress;
        $this->auth = $auth;
        $this->dispatcher = $dispatcher ?: new Dispatcher();
        $this->loop = $loop ?: LoopFactory::create();
        $this->connector = new Connector($this->loop);
        $this->tunnels = $this->createTunnels($tunnels);
        $this->tunnelClients = new TunnelClientCollection();
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
     * Creates array of tunnels
     * @param array $data
     * @return TunnelInterface[]
     */
    protected function createTunnels($data)
    {
        $tunnels = [];
        foreach ($data as $info) {
            $tunnel = TunnelFactory::fromArray($info);
            $tunnels[] = $tunnel;
        }
        return $tunnels;
    }

    /**
     * Run the client
     */
    public function run()
    {
        $this->connector->connect($this->serverAddress)->then(function(ConnectionInterface $connection){
            //Emit the event
            $this->dispatcher->dispatch(new Event(EventStore::CONNECT_TO_SERVER, $this, [
                'connection' => $connection
            ]));
            $this->controlConnection = $connection;
            $this->requestAuth($connection);
            $this->handleControlConnection($connection);
        });
        $this->dispatcher->dispatch(EventStore::CLIENT_RUN);
        foreach ($this->getDefaultTimers() as $timer) {
            $this->addTimer($timer);
        }
        $this->loop->run();
    }

    /**
     * Close the client
     */
    public function close()
    {
        foreach ($this->timers as $timer) {
            $timer->cancel();
        }
        foreach ($this->tunnelClients as $tunnelClient) {
            $tunnelClient->close();
        }
    }

    /**
     * Handles the control connection
     * @param ConnectionInterface $connection
     */
    protected function handleControlConnection(ConnectionInterface $connection)
    {
        $parser = new SpikeParser();
        $connection->on('data', function($data) use($parser, $connection){
            $parser->pushIncoming($data);
            $messages = $parser->parse();
            foreach ($messages as $message) {
                $message = Spike::fromString($message);
                $this->dispatcher->dispatch(new Event(EventStore::RECEIVE_MESSAGE, $this, [
                    'message' => $message,
                    'connection' => $connection
                ]));
                $this->createMessageHandler($message, $connection)->handle($message);
            }
        });
        $connection->on('close', function(){
            $this->close();
        });
    }

    /**
     * Request for auth
     * @param ConnectionInterface $connection
     */
    protected function requestAuth(ConnectionInterface $connection)
    {
        $authInfo = array_replace([
            'os' => PHP_OS,
            'version' => '',
        ], $this->auth);
        $connection->write(new Spike('auth', $authInfo));
    }

    /**
     * Sets the client id
     * @param string $id
     */
    public function setClientId($id)
    {
        $this->id = $id;
        Spike::setGlobalHeader('Client-ID', $id);
        $this->addTimer(new Heartbeat($this));
    }

    /**
     * Gets all tunnel clients
     * @return TunnelClientCollection
     */
    public function getTunnelClients()
    {
        return $this->tunnelClients;
    }

    /**
     * Gets all tunnels
     * @return TunnelInterface[]
     */
    public function getTunnels()
    {
        return $this->tunnels;
    }

    /**
     * Gets the dispatcher
     * @return Dispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * @return ConnectionInterface
     */
    public function getControlConnection()
    {
        return $this->controlConnection;
    }

    /**
     * Sets a logger
     * @param Logger $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Gets the logger
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Gets the loop instance
     * @return LoopInterface
     */
    public function getLoop()
    {
        return $this->loop;
    }

    /**
     * Finds the matching tunnel
     * @param array $tunnelInfo
     * @throws RuntimeException
     * @return false|TunnelInterface
     */
    public function findTunnel($tunnelInfo)
    {
        foreach ($this->tunnels as $tunnel) {
            if ($tunnel->match($tunnelInfo)) {
                return $tunnel;
            }
        }
        return false;
    }

    /**
     * Creates a tunnel client to process proxy connection
     * @param TunnelInterface $tunnel
     * @param string $proxyConnectionId
     * @return TunnelClientInterface
     */
    public function createTunnelClient(TunnelInterface $tunnel, $proxyConnectionId)
    {
        $tunnelClient = new TcpTunnelClient($this, $tunnel, $proxyConnectionId, $this->serverAddress, $this->loop);
        $tunnelClient->run();
        $this->tunnelClients->add($tunnelClient);
        return $tunnelClient;
    }

    /**
     * Creates the handler for the received message
     * @param SpikeInterface $message
     * @param ConnectionInterface $connection
     * @return Handler\HandlerInterface
     */
    protected function createMessageHandler(SpikeInterface $message, ConnectionInterface $connection)
    {
        switch ($message->getAction()) {
            case 'auth_response':
                $handler = new Handler\AuthResponseHandler($this, $connection);
                break;
            case 'register_tunnel_response':
                $handler = new Handler\RegisterTunnelResponseHandler($this, $connection);
                break;
            case 'request_proxy':
                $handler = new Handler\RequestProxyHandler($this, $connection);
                break;
            default:
                throw new InvalidArgumentException(sprintf('Cannot find handler for message type: "%s"',
                    get_class($message)
                ));
        }
        return $handler;
    }

    /**
     * Creates default timers
     * @return TimerInterface[]
     */
    protected function getDefaultTimers()
    {
        return [
            new MemoryWatcher($this->logger)
        ];
    }
}