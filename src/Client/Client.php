<?php

/*
 * This file is part of the slince/spike package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Spike\Client;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use function Slince\Common\jsonBuffer;
use Slince\Event\Dispatcher;
use Slince\Event\DispatcherInterface;
use Spike\Client\Event\Events;
use Spike\Client\Event\FilterActionHandlerEvent;
use Spike\Client\Listener\ClientListener;
use Spike\Client\Worker\WorkerInterface;
use Spike\Common\Protocol\Spike;
use Spike\Version;
use Slince\Event\Event;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Client extends Application implements ClientInterface
{
    /**
     * @var string
     */
    const NAME = 'spike-client';

    /**
     * @var string
     */
    protected $id;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var LoopInterface
     */
    protected $eventLoop;

    /**
     * @var DispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var ConnectionInterface
     */
    protected $controlConnection;

    /**
     * @var \DateTimeInterface
     */
    protected $activeAt;

    /**
     * @var WorkerInterface[]|Collection
     */
    protected $workers;

    public function __construct(Configuration $configuration, LoopInterface $eventLoop)
    {
        $this->configuration = $configuration;
        $this->eventLoop = $eventLoop ?: Factory::create();
        $this->eventDispatcher = new Dispatcher();
        $this->workers = new ArrayCollection();
        $this->initializeEvents();
        parent::__construct(static::NAME, Version::VERSION);
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        $connector = new Connector($this->eventLoop);
        $connector->connect($this->configuration->getServerAddress())->then([$this, 'handleControlConnection'], function(){
            $this->eventDispatcher->dispatch(new Event(Events::CANNOT_CONNECT_SERVER, $this));
        });
        $this->eventDispatcher->dispatch(Events::CLIENT_RUN);
        $this->eventLoop->run();
    }

    /**
     * Handles the control connection
     * @param ConnectionInterface $connection
     * @codeCoverageIgnore
     */
    protected function handleControlConnection(ConnectionInterface $connection)
    {
        $this->controlConnection = $connection;
        //Emit the event
        $this->eventDispatcher->dispatch(new Event(Events::CLIENT_CONNECT, $this, [
            'connection' => $connection
        ]));
        jsonBuffer($connection)->then(function($messages, $connection){
            foreach ($messages as $messageData) {
                $message = Spike::fromArray($messageData);

                //Fires filter action handler event
                $event = new FilterActionHandlerEvent($this, $message, $connection);
                $this->eventDispatcher->dispatch($event);

                if ($actionHandler = $event->getActionHandler()) {
                    $actionHandler->handle($message);
                }
            }
        })->then(null, function($exception) use ($connection){
            $this->eventDispatcher->dispatch(new Event(Events::CONNECTION_ERROR, $this, [
                'connection' => $connection,
                'exception' => $exception
            ]));
        });

        $connection->on('close', [$this, 'handleDisconnectServer']);

        //Sends auth request
        $this->sendAuthRequest($connection);
    }

    /**
     * Request for auth
     * @param ConnectionInterface $connection
     * @codeCoverageIgnore
     */
    protected function sendAuthRequest(ConnectionInterface $connection)
    {
        $authInfo = array_replace([
            'os' => PHP_OS,
            'version' => Version::VERSION,
        ], $this->configuration->get('auth', []));
        $connection->write(new Spike('auth', $authInfo));
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function getControlConnection()
    {
        return $this->controlConnection;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveAt()
    {
        return $this->activeAt;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * @param \DateTimeInterface $activeAt
     */
    public function setActiveAt($activeAt)
    {
        $this->activeAt = $activeAt;
    }

    /**
     * @return LoopInterface
     */
    public function getEventLoop()
    {
        return $this->eventLoop;
    }

    /**
     * @return Collection|WorkerInterface[]
     */
    public function getWorkers()
    {
        return $this->workers;
    }

    protected function initializeEvents()
    {
        $this->eventDispatcher->addSubscriber(new ClientListener());
    }
}