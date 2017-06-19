<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Server;

use React\Socket\ConnectionInterface;

class Client
{
    /**
     * id
     * @var string
     */
    protected $id;

    /**
     * Client information
     * @var array
     */
    protected $info;

    /**
     * @var ConnectionInterface
     */
    protected $controlConnection;

    public function __construct($info, ConnectionInterface $controlConnection)
    {
        $this->info = $info;
        $this->controlConnection = $controlConnection;
    }

    /**
     * Sets the control connection for the client
     * @param ConnectionInterface $controlConnection
     */
    public function setControlConnection($controlConnection)
    {
        $this->controlConnection = $controlConnection;
    }

    /**
     *
     * Gets the control connection of the client
     * @return ConnectionInterface
     */
    public function getControlConnection()
    {
        return $this->controlConnection;
    }

    /**
     * Gets the client id
     * @return string
     */
    public function getId()
    {
        return $this->id ?: ($this->id = spl_object_hash($this));
    }

    /**
     * Gets the client information
     * @return array
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * Gets the client information
     * @return array
     */
    public function toArray()
    {
        return array_replace($this->getInfo(), [
            'id' => $this->getId()
        ]);
    }
}