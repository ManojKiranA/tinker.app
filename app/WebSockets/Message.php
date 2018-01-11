<?php

namespace App\WebSockets;

use Illuminate\Contracts\Support\Jsonable;
use Ratchet\RFC6455\Messaging\MessageInterface;

class Message implements Jsonable
{
    const TERMINAL_DATA_TYPE = 1;

    /** @var int */
    protected $type;

    /** @var string */
    protected $payload;

    public function __construct(int $type, string $payload)
    {
        $this->type = $type;
        $this->payload = $payload;
    }

    public static function create(int $type, string $payload): self
    {
        return new static(func_get_args());
    }

    public static function terminalData(string $payload): self
    {
        return new static(static::TERMINAL_DATA_TYPE, $payload);
    }

    public static function from(MessageInterface $message): self
    {
        return new static(static::TERMINAL_DATA_TYPE, (string) $message);
    }

    public function toJson($options = 0): string
    {
        return json_encode([
            'type' => $this->type,
            'payload' => $this->payload,
        ], $options);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function getType(): int
    {
        return $this->type;
    }
}
