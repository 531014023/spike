<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Protocol;

class DomainRegisterRequest extends Request
{
    protected $domains;

    public function __construct(array $domains, $headers = [])
    {
        $this->domains = $domains;
        parent::__construct('register_domain', $headers);
    }

    public function getAddingDomains()
    {
        return $this->domains;
    }

    public function getBody()
    {
        return serialize($this->domains);
    }

    public function parseBody($body)
    {
        $this->domains = unserialize($this->domains);
    }

    public static function fromString($string)
    {
        $domains = unserialize($string);
        return new DomainRegisterRequest($domains);
    }
}