<?php

namespace ModularDiscord\Base;

use Discord\Discord;
use ModularDiscord\Base\Module;
use ModularDiscord\ModularDiscord;
use Psr\Log\LoggerInterface;

abstract class Accessor
{
    public readonly string $name;
    public readonly LoggerInterface $logger;
    public abstract function get(): mixed;

    public function __construct(ModularDiscord $modularDiscord, string $name)
    {
        $this->name = $name;
        $this->logger = $modularDiscord->createLogger('Accessor#' . get_class($this));
    }

    public function onDiscordReady(Discord $discord) {}
    public function onModuleReady(Module $module) {}

    public function consoleCall() {}

    public abstract function load();
    public function close() {}
}