<?php

namespace ModularDiscord\Base;

use Discord\Discord;
use Discord\WebSockets\Event;
use ModularDiscord\ModularDiscord;
use ModularDiscord\Registry;
use Psr\Log\LoggerInterface;

class Module
{
    public readonly ModularDiscord $modularDiscord;
    public readonly LoggerInterface $logger;
    public readonly Registry $registry;
    public readonly string $name, $path;
    private bool $disabled = false;

    public bool $callReadyOnEnable = false;

    public function __construct(string $name, string $path, ModularDiscord $modularDiscord)
    {
        $this->name = $name;
        $this->modularDiscord = $modularDiscord;
        $this->path = $path;
        $this->logger = $modularDiscord->createLogger('Module#'.$name);
        $this->registry = new Registry($modularDiscord, $this);
    }

    public function onEnable() {}
    public function onDisable() {}
    public function onClose(bool $unexpected = false) {}
    public function onDiscordInit(Discord $discord) {}
    public function onDiscordReady(Discord $discord) {}

    public final function isFirstTimeLoad(): bool
    {
        return !isset($this->modularDiscord->getModules()[$this->name]);
    }

    public final function setEnabled(bool $bool)
    {
        $this->disabled = !$bool;
        if ($bool) {
            $this->onEnable();
        } else {
            $this->onDisable();
            $this->registry->removeDiscordListeners();
        }
    }

    public final function isEnabled(): bool
    {
        return !$this->disabled;
    }
}