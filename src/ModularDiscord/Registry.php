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
    private function __construct() {}

    public static function registerListener(Module $module, Discord $discord, Listener $listener, array &$listeners = [])
    {
        $methods = get_class_methods($listener);

        foreach ($methods as $method)
            if (str_starts_with($method, 'on')) {
                $name = substr($method, 2);
                $reflection = new ReflectionClass('Discord\WebSockets\Event');
                $refMethod = new ReflectionMethod($listener, $method);
                $constants = $reflection->getConstants();
                $event = null;
                foreach ($constants as $constant)
                    if (str_replace('_', '', strtolower($constant)) == strtolower($name)) {
                        $event = $reflection->getConstant($constant);
                        break;
                    }
                if ($event != null) {
                    $discord->on($event, $closure = $refMethod->getClosure($listener));
                    $listeners[$event] = $closure;
                    continue;
                }
                //$module->logger->warning("Couldn't find event that is close to " . ucfirst($name));
            }

        $c = count($listeners);
        $module->logger->info("Registered $c listener" . ($c > 1 ? 's' : ''), [
            'listeners' => array_keys($listeners)
        ]);
    }

    public static function registerCommand(Module $module, Discord $discord, Command $command)
    {

    }
}