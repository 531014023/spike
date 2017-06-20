<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Server\Handler;

use Spike\Protocol\SpikeInterface;
use Spike\Protocol\Spike;
use Spike\Server\Client;

class AuthHandler extends MessageHandler
{
    public function handle(SpikeInterface $message)
    {
        $auth = $message->getBody();
        if ($this->server->getAuthentication()->verify($auth)) {
            $client = new Client($message->getBody(), $this->connection);
            $this->server->getClients()->add($client);
            $response = new Spike('auth_response', $client->toArray());
        } else {
            $response = new Spike('auth_response', $auth, [
                'Code' =>  200
            ]);
        }
        $this->connection->write($response);
    }
}