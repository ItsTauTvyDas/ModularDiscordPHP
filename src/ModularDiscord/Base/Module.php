<?php

namespace ModularDiscord\Base;

use Discord\Discord;
use ModularDiscord\ModularDiscord;
use Psr\Log\LoggerInterface;

abstract class Module
{
    public readonly ModularDiscord $modularDiscord;
    public readonly LoggerInterface $logger;
    public readonly string $name;

    public function __construct(string $name, ModularDiscord $modularDiscord)
    {
        $this->name = $name;
        $this->modularDiscord = $modularDiscord;
        $this->logger = $modularDiscord->createLogger('Module#'.$name);
    }

    public function onEnable() {}
    public function onDisable() {}
    public function onClose() {}
    public function onDiscordInit(Discord $discord) {}
    public function onDiscordReady(Discord $discord) {}
}