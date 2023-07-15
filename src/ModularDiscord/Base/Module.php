<?php

namespace ModularDiscord\Base;

use Discord\Discord;
use Discord\WebSockets\Event;
use ModularDiscord\ModularDiscord;
use Psr\Log\LoggerInterface;

class Module
{
    public readonly ModularDiscord $modularDiscord;
    public readonly LoggerInterface $logger;
    public readonly string $name, $path;
    private bool $disabled = false;

    public function __construct(string $name, string $path, ModularDiscord $modularDiscord)
    {
        $this->name = $name;
        $this->modularDiscord = $modularDiscord;
        $this->path = $path;
        $this->logger = $modularDiscord->createLogger('Module#'.$name);
    }

    public function onEnable(bool $firstLoad = false) {}
    public function onDisable() {}
    public function onClose(bool $unexpected = false) {}
    public function onDiscordInit(Discord $discord) {}
    public function onDiscordReady(Discord $discord) {}

    public final function setEnabled(bool $bool)
    {
        $this->disabled = !$bool;
        if ($bool and $this->disabled)
            $this->onEnable();
        else if (!$bool and !$this->disabled)
            $this->onDisable();
    }

    public final function isEnabled(): bool
    {
        return !$this->disabled;
    }
}