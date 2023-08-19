<?php

use Discord\Parts\Channel\Message;
use ModularDiscord\Base\Listener;
use ModularDiscord\ModularDiscord;

class MyListener implements Listener
{
    private ModularDiscord $mod;
    private Ping $pinger;

    public function __construct(ModularDiscord $mod)
    {
        $this->mod = $mod;
        $this->pinger = $this->mod->accessor("PingAccessor")->get();
    }

    public function onMessageCreate(Message $message): void
    {
        if (!$message->author->bot and $message->content == 'ping')
            $message->reply($this->pinger->ping());
    }
}