<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Server\TunnelServer;

use React\Socket\ConnectionInterface;
use Spike\Server\Tunnel\TunnelInterface;

interface TunnelServerInterface
{
    /**
     * Gets the tunnel
     * @return TunnelInterface
     */
    public function getTunnel();

    /**
     * Run the server
     */
    public function run();

    /**
     * Pause the server
     */
    public function pause();

    /**
     * Resumes the server
     */
    public function resume();

    /**
     * Handles the proxy connection
     * @param ConnectionInterface $connection
     */
    public function handleProxyConnection(ConnectionInterface $connection);
}