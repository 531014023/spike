<?php
namespace Spike\Tests\Server\Handler;

use Spike\Exception\BadRequestException;
use Spike\Protocol\Spike;
use Spike\Server\Client;
use Spike\Server\Handler\RegisterProxyHandler;
use Spike\Tests\TestCase;

class RegisterProxyHandlerTest extends TestCase
{
    public function testExecute()
    {
        $ChunkServer = $this->getChunkServerMock();

        $client = new Client([
            'os' => PHP_OS,
            'version' => '',
        ], $ChunkServer->getControlConnection());

        $server = $ChunkServer->getServer();
        $server->getClients()->add($client);

        $server->getChunkServers()->add($ChunkServer);

        $handler = new RegisterProxyHandler($server, $ChunkServer->getControlConnection());
        $message = new Spike('register_proxy', [
            'serverPort' => 8086
        ], [
            'Client-ID' => $client->getId()
        ]);
        $handler->handle($message);
    }

    public function testWrongTunnelInfo()
    {
        $ChunkServer = $this->getChunkServerMock();

        $client = new Client([
            'os' => PHP_OS,
            'version' => '',
        ], $ChunkServer->getControlConnection());

        $server = $ChunkServer->getServer();
        $server->getClients()->add($client);

        $server->getChunkServers()->add($ChunkServer);

        $handler = new RegisterProxyHandler($server, $ChunkServer->getControlConnection());
        $message = new Spike('register_proxy', [
            'serverPort' => 9999999
        ], [
            'Client-ID' => $client->getId()
        ]);
        $this->expectException(BadRequestException::class);
        $handler->handle($message);
    }
}