<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Protocol;

use Spike\Exception\BadRequestException;

abstract class Spike implements SpikeInterface
{
    /**
     * The action
     * @var string
     */
    protected $action;

    /**
     * Array of custom headers
     * @var array
     */
    protected $headers = [];

    public function __construct($action, $headers = [])
    {
        $this->action = $action;
        $this->headers = $headers;
    }

    public function __toString()
    {
        return $this->toString();
    }

    /**
     * {@inheritdoc}
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * {@inheritdoc}
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * {@inheritdoc}
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($name)
    {
        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function toString()
    {
        $body = $this->getBody();
        $headers = array_merge([
            'Spike-Action' => $this->action,
            'Spike-Version' => SpikeInterface::VERSION,
            'Content-Length' => strlen($body)
        ], $this->getHeaders());
        $buffer = '';
        foreach ($headers as $header => $value) {
            $buffer .= "{$header}: {$value}\r\n";
        }
        return $buffer
            . "\r\n\r\n"
            . $body;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromString($string)
    {
        list($headers, $bodyBuffer) = Spike::parseMessages($string);
        if (!isset($headers['Spike-Action'])) {
            throw new BadRequestException('Missing value for the header "action"');
        }
        $bodyBuffer = trim($bodyBuffer);
        return new static(static::parseBody($bodyBuffer), $headers);
    }

    /**
     * Parses the message
     * @param string $message
     * @return array
     */
    public static function parseMessages($message)
    {
        list($headerBuffer, $bodyBuffer) = explode("\r\n\r\n", $message, 2);
        $lines = preg_split('/(\\r?\\n)/', $headerBuffer, -1, PREG_SPLIT_DELIM_CAPTURE);
        $headers = [];
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            $header = trim($parts[0]);
            $headers[$header] = isset($parts[1]) ? trim($parts[1]) : null;
        }
        return [$headers, trim($bodyBuffer)];
    }
}