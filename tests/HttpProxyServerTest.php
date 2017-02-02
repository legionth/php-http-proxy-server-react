<?php

use Legionth\React\Http\Proxy\HttpProxyServer;
use React\Socket\Server as Socket;
use React\Socket\Connection;
use React\Promise\Promise;

class HttpProxyServerTest extends TestCase
{
    private $loop;
    private $clientToProxyConnection;

    public function setUp()
    {
        $this->loop = new React\EventLoop\StreamSelectLoop();

        $this->clientToProxyConnection = $this->getMockBuilder('React\Socket\Connection')
            ->disableOriginalConstructor()
            ->setMethods(
                array(
                    'write',
                    'end',
                    'close',
                    'pause',
                    'resume',
                    'isReadable',
                    'isWritable'
                )
            )
            ->getMock();
    }

    public function testSimpleRequest()
    {
        $request = "GET /ip HTTP/1.1\r\nHost: localhost\r\n\r\n";

        $connector = $this->getMockBuilder('React\SocketClient\DnsConnector')
            ->disableOriginalConstructor()
            ->getMock();

        $proxyToServerConnection = $this->getMockBuilder('React\Socket\Connection')
            ->disableOriginalConstructor()
            ->setMethods(
                array(
                    'write',
                    'end',
                    'close',
                    'pause',
                    'resume',
                    'isReadable',
                    'isWritable'
                )
            )
            ->getMock();


        $promise = new Promise(
            function ($resolve, $reject) use ($proxyToServerConnection) {
                $resolve($proxyToServerConnection);
            }
        );

        $connector->expects($this->once())
            ->method('create')
            ->with($this->equalTo('localhost'), $this->equalTo(80))
            ->willReturn($promise);

        $socket = new Socket($this->loop);
        $server = new HttpProxyServer($socket, $connector);

        $socket->emit('connection', array($this->clientToProxyConnection));
        $this->clientToProxyConnection->expects($this->once())->method('write')->with($this->equalTo("HTTP/1.1 200 OK\r\n\r\n"));
        $this->clientToProxyConnection->emit('data', array($request));
        $proxyToServerConnection->emit('data', array("HTTP/1.1 200 OK\r\n\r\n"));
    }
}
