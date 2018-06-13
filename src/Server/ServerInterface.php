<?php

/*
 * This file is part of the slince/spike package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spike\Server;

use Doctrine\Common\Collections\Collection;
use React\Socket\ConnectionInterface;
use Slince\Event\Dispatcher;

interface ServerInterface
{
    /**
     * Handle control connection
     *
     * @param ConnectionInterface $connection
     */
    public function handleControlConnection(ConnectionInterface $connection);

    /**
     * @return Collection
     */
    public function getChunkServers();

    /**
     * Starts the server
     */
    public function start();
}