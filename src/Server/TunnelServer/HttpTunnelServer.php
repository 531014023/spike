<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Server\TunnelServer;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7;
use Spike\Parser\HttpHeaderParser;

class HttpTunnelServer extends TunnelServer
{
    /**
     * {@inheritdoc}
     */
    public function handleProxyConnection(ProxyConnection $proxyConnection)
    {
        $parser = new HttpHeaderParser();
        $proxyConnection->getConnection()->on('data', function($data) use ($parser, $proxyConnection){
            $parser->pushIncoming($data);
            $message = $parser->parseFirst();
            echo $message;
            if ($message) {
                $psrRequest = Psr7\parse_request($message);
                $host = $psrRequest->getUri()->getHost();
                if ($this->tunnel->supportProxyHost($host)) {
                    $this->tunnel->setProxyHost($host);
                    $httpMessage = $message . $parser->getRestData();
                    $proxyConnection->setInitBuffer($httpMessage);
                    parent::handleProxyConnection($proxyConnection);
                } else {
                    $body = sprintf('The host "%s" was not bound.', $host);
                    $response = $this->makeErrorResponse(404, $body);
                    $proxyConnection->end(Psr7\str($response));
                }
            }
        });
    }

    /**
     * Make an error psr7 response
     * @param int $code
     * @param string $message
     * @return Response
     */
    protected function makeErrorResponse($code, $message)
    {
        $message = $message ?: 'Proxy error';
        return new Response($code, [
            'Content-Length' => strlen($message)
        ], $message);
    }

    /**
     * {@inheritdoc}
     */
    public function closeProxyConnection(ProxyConnection $proxyConnection, $message = null)
    {
        $proxyConnection->end(Psr7\str($this->makeErrorResponse(500, $message ?: 'Timeout')));
    }
}