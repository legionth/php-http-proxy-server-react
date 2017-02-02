<?php

use React\Socket\Server as Socket;
use React\SocketClient\DnsConnector;
use React\SocketClient\TcpConnector;
use Legionth\React\Http\Proxy\HttpProxyServer;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$socket = new Socket($loop);
$socket->listen(10001, 'localhost');

$connector = new TcpConnector($loop);
$resolverFactory = new React\Dns\Resolver\Factory();
$resolver = $resolverFactory->create('8.8.8.8', $loop);
$dnsConnector = new DnsConnector($connector, $resolver);

$server = new HttpProxyServer($socket, $dnsConnector);

$loop->run();
