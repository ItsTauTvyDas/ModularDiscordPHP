<?php

namespace ModularDiscord;

use Discord\Discord;
use ModularDiscord\Base\Command;
use ModularDiscord\Base\Listener;
use ModularDiscord\Base\Module;
use ReflectionClass;
use ReflectionMethod;

final class Registry
{
    private readonly ModularDiscord $modularDiscord;
    private readonly Module $module;
    private array $listeners = [];

    public function __construct(ModularDiscord $modularDiscord, Module $module)
    {
        $this->modularDiscord = $modularDiscord;
        $this->module = $module;
    }

    public function registerListener(Listener $listener, array &$listeners = [])
    {
        $methods = get_class_methods($listener);

        $count = 0;
        foreach ($methods as $method)
            if (str_starts_with($method, 'on')) {
                $name = substr($method, 2);
                $reflection = new ReflectionClass('Discord\WebSockets\Event');
                $refMethod = new ReflectionMethod($listener, $method);
                $event = current(array_filter($reflection->getConstants(), fn ($const) => str_replace('_', '', strtolower($const)) == strtolower($name)));
                if (is_string($event)) {
                    $this->modularDiscord->discord->on($event, $closure = $refMethod->getClosure($listener));
                    $listeners[$event] = $closure;
                    $this->listeners[$event][] = $closure;
                    $count++;
                    continue;
                }
            }

        $this->module->logger->info("Registered $count listener" . ($count > 1 ? 's' : ''), [
            'listeners' => array_keys($listeners),
            'class' => get_class($listener)
        ]);
    }

    public function removeDiscordListeners()
    {
        $count = 0;
        foreach ($this->listeners as $key => $value) {
            foreach ($value as $listener) {
                $this->modularDiscord->discord->removeListener($key, $listener);
            }
        }
        if ($count)
            $this->module->logger->info("Unegistered $count listener" . ($count > 1 ? 's' : ''), [
                'listeners' => array_keys($this->listeners),
                'class' => get_class($listener)
            ]);
    }

    public function registerCommand(Command $command)
    {

    }

    public function getListeners(): array
    {
        return $this->listeners;
    }
}