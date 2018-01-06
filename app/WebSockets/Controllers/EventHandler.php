<?php

namespace App\WebSockets\Controllers;

use \App\WebSockets\Client;
use \Ratchet\MessageComponentInterface;
use App\Services\Docker\Container;
use App\Services\Docker\ContainerRepository;
use Exception;
use GuzzleHttp\Psr7\Request;
use PartyLine;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use SplObjectStorage;

class EventHandler implements MessageComponentInterface
{
    /** @var \App\Services\Docker\ContainerRepository */
    protected $containerRepository;

    /** @var \SplObjectStorage */
    protected $clients;

    protected $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;

        $this->clients = new SplObjectStorage();

        $this->containerRepository = new ContainerRepository($loop);
    }

    public function onOpen(ConnectionInterface $browserConnection)
    {
        PartyLine::comment("New connection! ({$browserConnection->resourceId})");

        $browserConnection->send("Loading Tinker session...\n\r");

        $client = new Client($browserConnection, $this->loop);

        $sessionId = $this->getQueryParam($browserConnection->httpRequest, 'sessionId');

        $container = $this->getContainer($sessionId);

        if (!$container) {
            return;
        }

        $client->attachContainer($container);

        $this->clients->attach($client);
    }

    public function onMessage(ConnectionInterface $from, $message)
    {
        $client = $this->getClientForConnection($from);

        $client->sendToTinker($message);

        PartyLine::comment("Connection {$from->resourceId} sending message `{$message}` to other connection");
    }

    public function onClose(ConnectionInterface $browserConnection)
    {
        PartyLine::comment("Connection {$browserConnection->resourceId} has disconnected");

        $client = $this->getClientForConnection($browserConnection);

        if ($client) {
            $client->cleanupContainer();
            $this->clients->detach($client);
        }
    }

    public function onError(ConnectionInterface $browserConnection, Exception $exception)
    {
        PartyLine::error("An error has occurred: {$exception->getMessage()}");

        $browserConnection->close();
    }

    protected function getContainer(string $sessionId, ConnectionInterface $browserConnection): ?Container
    {
        if ($sessionId) {
            $container = $this->containerRepository->findBySessionId($sessionId);

            if (!$container) {
                $browserConnection->send("Session id `{$sessionId}` is invalid.\n\r");
                $browserConnection->close();

                return null;
            }

            $browserConnection->send("Session id `{$sessionId}` found.\n\r");

            return $container;
        }

        $container = (Container::create($this->loop))->start();

        $browserConnection->send("New Tinker session created ({$container->getName()})\n\r");

        return $container;
    }

    protected function getClientForConnection(ConnectionInterface $browserConnection): ?Client
    {
        return collect($this->clients)->first(function ($client, $key) use ($browserConnection) {
            return $client->getConnection() === $browserConnection;
        });
    }

    protected function getQueryParam(Request $request, string $key): ?string
    {
        parse_str($request->getUri()->getQuery(), $queryParams);

        return $queryParams[$key] ?: null;
    }
}
