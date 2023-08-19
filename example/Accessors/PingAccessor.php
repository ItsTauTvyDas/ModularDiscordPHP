<?php

use ModularDiscord\Base\Accessor;

class Ping
{
    public function ping(): string
    {
        return 'pong!';
    }
}

class PingAccessor extends Accessor
{
    private readonly Ping $object;

    public function load(): void
    {
        $this->object = new Ping();
    }

    public function get(): Ping
    {
        return $this->object;
    }
}