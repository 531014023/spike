<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Buffer;

use React\Socket\ConnectionInterface;

class FixedLengthBuffer extends Buffer
{
    /**
     * Bytes, buffer length
     * @var int
     */
    protected $length;

    public function __construct(ConnectionInterface $connection, $length)
    {
        parent::__construct($connection);
        $this->length = $length;
        $this->connection->on('data', [$this, 'handleData']);
    }

    public function handleData($data)
    {
        $this->content .= $data;
        if (strlen($this->content) >= $this->length) {
            $this->message = substr($this->content, 0, $this->length);
            $this->gatherComplete();
        }
    }
}