<?php

namespace App\Loop;

/**
 * Class Constants
 *
 * @package Encry\ClientEventBundle\Loop
 */
class Constants
{
    const SOCKET_ADDRESS = '/tmp/event.sock';

    const SOCKET_CHANNEL_CLIENT_JOB_READY = 'count.ready';
    const SOCKET_CHANNEL_CLIENT_SETTINGS = 'settings';
    const SOCKET_CHANNEL_CLIENT_CONNECT = 'connect';
    const SOCKET_CHANNEL_PROCESS_STOP = 'stop';
    const SOCKET_CHANNEL_PING = 'ping';
    const SOCKET_CHANNEL_PONG = 'pong';

    const CHANNEL_CLIENT_CONSOLE = 'client.console';
    const CHANNEL_CLIENT_WRITE = 'client.write';
    
    const START_PROCESS_EVENT = 'process.start';
    const STOP_PROCESS_EVENT = 'process.stop';
    const RESTART_PROCESS_EVENT = 'process.restart';
    const ERROR_PROCESS_EVENT = 'process.error';
    const ERROR_CRON_PROCESS_EVENT = 'cron.process.error';
    const EXIT_PROCESS_EVENT = 'process.exit';
}
