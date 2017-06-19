<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Tunnel;

class HttpTunnel extends Tunnel
{
    /**
     * @var array
     */
    protected $proxyHosts;

    protected $proxyHost;

    public function __construct($serverPort, $proxyHosts)
    {
        $this->proxyHosts = $proxyHosts;
        parent::__construct($serverPort);
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
     * @return string
     */
    public function getProxyHost()
    {
        return $this->proxyHost;
    }

    /**
     * Checks whether the tunnel supports the host
     * @param string $proxyHost
     * @return bool
     */
    public function supportProxyHost($proxyHost)
    {
        return isset($this->proxyHosts[$proxyHost]);
    }

    /**
     * Gets the forward host of the proxy host
     * @param string $proxyHost
     * @return string|null
     */
    public function getForwardHost($proxyHost)
    {
        return isset($this->proxyHosts[$proxyHost]) ?
            $this->proxyHosts[$proxyHost] : null;
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
            'protocol' => 'http',
            'proxyHosts' => $this->proxyHosts,
            'serverPort' => $this->serverPort,
            'proxyHost' => $this->proxyHost,
        ];
    }
}