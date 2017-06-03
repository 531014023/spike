<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Protocol;

use Spike\Exception\BadResponseException;

abstract class SpikeResponse extends Spike
{
    /**
     * The status code of the response
     * @var int
     */
    protected $code;

    public function __construct($code, $action, $headers = [])
    {
        $this->code = $code;
        parent::__construct($action, $headers);
    }

    /**
     * @param int $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    public function getHeaders()
    {
        return array_replace(parent::getHeaders(), [
            'code' => $this->code
        ]);
    }

    public static function fromString($string)
    {
        list($headers, $bodyBuffer) = Spike::parseMessages($string);
        if (!isset($headers['Spike-Action']) || !isset($headers['Code'])) {
            throw new BadResponseException('Missing value');
        }
        $bodyBuffer = trim($bodyBuffer);
        return new static(trim($headers['Code']), static::parseBody($bodyBuffer), $headers);
    }
}