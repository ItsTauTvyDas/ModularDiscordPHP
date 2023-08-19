<?php

use Discord\Discord;
use ModularDiscord\Base\Module;

class HelloWorld extends Module
{
    public function onEnable(): void
    {
        $this->callReadyOnEnable = true;
        $this->cacheListeners = true;
    }

    /**
     * @throws Exception
     */
    public function onDiscordReady(Discord $discord): void
    {
        $this->logger->info("Hello world from module!");
        $this->registry->registerListener(new MyListener($this->modularDiscord));
        $this->registry->registerCommand(new TestCommand($this), 'hello', 'Greet a person');
    }

    public function consoleCall(string ...$params): void
    {
        $this->logger->info("Hello there :3");
    }

    public function loadLocalFiles(): void
    {
        require 'listener.php';
        require 'command.php';
    }
}