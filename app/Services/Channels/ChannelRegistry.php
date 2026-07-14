<?php

namespace App\Services\Channels;

use App\Contracts\MessagingChannel;
use InvalidArgumentException;

class ChannelRegistry
{
    public function __construct(private TelegramChannel $telegram) {}

    public function get(string $key): MessagingChannel
    {
        return match ($key) {
            $this->telegram->key(), 'telegram' => $this->telegram,
            default => throw new InvalidArgumentException("Channel {$key} belum terdaftar."),
        };
    }
}
