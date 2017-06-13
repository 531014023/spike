<?php
/**
 * Spike library
 * @author Tao <taosikai@yeah.net>
 */
namespace Spike\Client;

final class EventStore
{
    /**
     * Emit when the client begin run
     * @var string
     */
    const CLIENT_RUN = 'client_run';

    /**
     * Emit when the client connect to a server
     * @var string
     */
    const CONNECT_TO_SERVER =  'connect_to_server';

    /**
     * Emit when server socket has error
     * @var string
     */
    const SOCKET_ERROR = 'socket_error';

    /**
     * Emit when client registers array of tunnels to the server
     * @var string
     */
    const REGISTER_TUNNELS = 'register_tunnels';

    /**
     * Emit when client accepts a new connection
     * @var string
     */
    const ACCEPT_CONNECTION = 'accept_connection';

    /**
     * Emit when connection error
     * @var string
     */
    const CONNECTION_ERROR = 'connection_error';

    /**
     * Emit when client receive message
     * @var string
     */
    const RECEIVE_MESSAGE = 'receive_message';

    const REGISTER_TUNNEL_ERROR = 'register_tunnel_error';

    const REGISTER_TUNNEL_SUCCESS = 'register_tunnel_success';

    /**
     * Emit when client receives message
     * @var string
     */
    const RECEIVE_PROXY_REQUEST = 'receive_proxy_request';

    /**
     * Emit when client sends a request response message
     * @var string
     */
    const SEND_PROXY_RESPONSE = 'send_proxy_response';
}