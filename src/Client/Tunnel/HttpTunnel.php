<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Client\Tunnel;

class HttpTunnel extends Tunnel
{
    protected $hosts;

    public function __construct($remotePort, $hosts)
    {
        parent::__construct(static::TUNNEL_HTTP, $remotePort);
        $this->hosts = $hosts;
    }

    /**
     * @return mixed
     */
    public function getHosts()
    {
        return $this->hosts;
    }

    public function getLocalHost($proxyHost)
    {
        return isset($this->hosts[$proxyHost]) ? $this->hosts[$proxyHost] : null;
    }

    public function toArray()
    {
        return [
            'protocol' => $this->protocol,
            'remotePort' => $this->remotePort,
            'proxyHosts' => array_keys($this->hosts),
        ];
    }
}