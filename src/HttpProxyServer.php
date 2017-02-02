<?php
namespace Legionth\React\Http\Proxy;

use Legionth\React\Http\HttpServer;
use Legionth\React\Http\HttpBodyStream;
use React\SocketClient\ConnectorInterface;
use React\Socket\ServerInterface;
use Psr\Http\Message\RequestInterface;
use React\Promise\Promise;
use RingCentral\Psr7\Response;
use React\Socket\ConnectionInterface;
use RingCentral\Psr7;
use RingCentral;

class HttpProxyServer
{
    private $connector;

    public function __construct(ServerInterface $socket, ConnectorInterface $connector)
    {
        $this->socket = $socket;

        $that = $this;
        $this->connector = $connector;

        $callback = function (RequestInterface $request) use ($that) {
            return $that->handleRequest($request);
        };
        $this->server = new HttpServer($socket, $callback);
    }

    public function handleRequest(RequestInterface $request)
    {
        $that = $this;
        $connector = $this->connector;

        return new Promise(function ($resolve, $reject) use ($request, $connector, $that) {
            $host = $request->getHeader('Host')[0];

            $urlArray = parse_url($host);

            if ($urlArray === false) {
                $resolve(new Response(502));
                return;
            }

            if (!isset($urlArray['host'])) {
                if (!isset($urlArray['path'])) {
                    $resolve(new Response(502));
                    return;
                }
                $host = $urlArray['path'];
            }

            $port = 80;
            if (isset($urlArray['port'])) {
                $port = $urlArray['port'];
            }

            $headerBuffer = '';
            echo "Host: " . $host . " Port: " . $port . "\n";
            $connector->create($host, $port)->then(
                function (ConnectionInterface $stream) use ($that, $request, $resolve, $headerBuffer) {
                    $body = $request->getBody();
                    $body->on('data', function ($chunk) use ($resolve, $stream) {
                        $stream->write($chunk);
                    });

                    $headerBuffer = '';

                    $listener = function ($data) use (&$headerBuffer, $stream, &$listener, $resolve){
                        $headerBuffer .= $data;
                        if (strpos($headerBuffer, "\r\n\r\n") !== false) {
                            $stream->removeListener('data', $listener);
                            // header is completed
                            $fullHeader = (string)substr($headerBuffer, 0, strpos($headerBuffer, "\r\n\r\n") + 4);

                            try {
                                $response = RingCentral\Psr7\parse_response($fullHeader);
                            } catch (\Exception $ex) {
                                $that->sendResponse(new Response(400), $connection);
                                return;
                            }
                            if ($response->hasHeader('Content-Length')) {
                                $contentLength = $request->getHeaderLine('Content-Length');

                                $int = (int) $contentLength;
                                if ((string)$int !== (string)$contentLength) {
                                    // Send 400 status code if the value of 'Content-Length'
                                    // is not an integer or is duplicated
                                    $that->sendResponse(new Response(400), $connection);
                                    return;
                                }
                            }

//                             $bodyStream = new HttpBodyStream($stream);
//                             $response = $response->withBody($bodyStream);
//                             $stream->write(RingCentral\Psr7\str($response));
                            $resolve($response);
                            // remove header from $data, only body is left
                            $data = (string)substr($data, strlen($fullHeader));
                            if ($data !== '') {
                                $stream->emit('data', array($data));
                            }
                        }
                    };
                    $stream->on('data', $listener);
                }
            )->then(null, function ($ex) {
                echo "fu" . $ex;
            });
        });
    }

    /** @internal */
    public function sendResponse(ResponseInterface $response, ConnectionInterface $connection)
    {
        $connection->write(RingCentral\Psr7\str($response));
        $connection->pause();
        $connection->end();
    }
}
