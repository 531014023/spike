<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Tunnel;

use React\Socket\ConnectionInterface;

class HttpTunnel extends Tunnel
{
    /**
     * @var array
     */
    protected $proxyHosts;

    protected $proxyHost;

    public function __construct($serverPort, $proxyHosts, ConnectionInterface $controlConnection = null)
    {
        $this->proxyHosts = $proxyHosts;
        parent::__construct($serverPort, $controlConnection);
    }

    /**
     * Gets all proxy hosts
     * @return array
     */
    public function getProxyHosts()
    {
        return $this->proxyHosts;
    }

    /**
     * @param mixed $proxyHost
     */
    public function setProxyHost($proxyHost)
    {
        $this->proxyHost = $proxyHost;
    }

    /**
     * @return mixed
     */
    public function getProxyHost()
    {
        return $this->proxyHost;
    }

    /**
     * Checks whether the tunnel supports the host
     * @param string $host
     * @return bool
     */
    public function supportProxyHost($host)
    {
        return in_array($host, $this->proxyHosts);
    }

    /**
     * {@inheritdoc}
     */
    public function match($info)
    {
        return parent::match($info)
            &&  (!isset($info['proxyHost'])
                || $this->supportProxyHost($info['proxyHost']));
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return [
            'proxyHosts' => $this->proxyHosts,
            'serverPort' => $this->serverPort,
            'proxyHost' => $this->proxyHost,
        ];
    }
}