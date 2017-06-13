<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Client\Handler;

use Spike\Client\ProxyContext;
use Spike\Client\Tunnel\HttpTunnel;
use Spike\Protocol\MessageInterface;

class RequestProxyHandler extends MessageHandler
{
    public function handle(MessageInterface $message)
    {
        $tunnelInfo = $message->getBody();
        $tunnel = $this->findTunnel($tunnelInfo);
        if ($tunnel instanceof HttpTunnel) {
            if ($tunnel->supportProxyHost($tunnelInfo['proxyHost'])) {
                $proxyContext = new ProxyContext($tunnel, [
                    'proxyHost' => $tunnelInfo['proxyHost']
                ]);
                $this->client->setProxyContext($proxyContext);
            }
        }
        $this->client->createTunnelClient($this->connection);
    }

    protected function findTunnel($info)
    {
        foreach ($this->client->getTunnels() as $tunnel) {
            if ($tunnel->getRemotePort() == $info['port']) {
                return $tunnel;
            }
        }
        return false;
    }
}