<?php

namespace ModularDiscord;

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
                    if ($this->module->cacheListeners)
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

    /**
     * Unregisters cached listeners if there's any.
     * Note: This gets called when disabling module.
     */
    public function removeDiscordListeners()
    {
        $count = 0;
        foreach ($this->listeners as $key => $value) {
            foreach ($value as $listener) {
                $this->modularDiscord->discord->removeListener($key, $listener);
                $count++;
            }
        }
        if ($count)
            $this->module->logger->info("Unegistered $count listener" . ($count > 1 ? 's' : ''), array_keys($this->listeners));
    }

    public function registerCommand(Command $command)
    {

    }

    public function getListeners(): array
    {
        return $this->listeners;
    }
}