<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Protocol;

use Spike\Server\Exception\BadResponseException;

abstract class Response extends Message implements ResponseInterface
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

    public function toString()
    {
        $headers = array_merge($this->headers, [
            'Version' => static::VERSION,
            'Action' => $this->action,
            'Code' => $this->code
        ]);
        $buffer = '';
        foreach ($headers as $header) {
            $buffer .= ": {$header}\r\n";
        }
        return $buffer
            . "\r\n\r\n"
            . $this->getBody();
    }

    public static function fromString($string)
    {
        list($headers, $bodyBuffer) = Message::parseMessages($string);
        if (!isset($headers['Action']) || !isset($headers['Code'])) {
            throw new BadResponseException('Missing value');
        }
        $bodyBuffer = trim($bodyBuffer);
        return new static(trim($headers['code']), static::parseBody($bodyBuffer), $headers);
    }
}