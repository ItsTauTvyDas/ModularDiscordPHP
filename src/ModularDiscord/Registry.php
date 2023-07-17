<?php

namespace ModularDiscord;

use Discord\Parts\Interactions\Command\Command;
use Discord\Parts\Interactions\Interaction;
use ModularDiscord\Base\AbstractCommand;
use ModularDiscord\Base\Listener;
use ModularDiscord\Base\Module;
use ReflectionClass;
use ReflectionMethod;

final class Registry
{
    private readonly ModularDiscord $modularDiscord;
    private readonly Module $module;
    private array $listeners = [];

    public function __construct(Module $module)
    {
        $this->modularDiscord = $module->modularDiscord;
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

    public function isCommandCached(string $name): bool
    {
        return in_array($name, $this->modularDiscord->cache->getCached(null, Cache::COMMANDS, []));
    }

    public function registerCommand(AbstractCommand $command, bool $cacheCommand = true, ?string $name = null, ?string $description = null, ?string $saveReason = null)
    {
        $builder = $command->onCreate();
        if ($name != null and !isset($builder->name))
            $builder->setName($name);
        if ($description != null and !isset($builder->description))
            $builder->setDescription($description);

        $name = $builder->name;
        $description = $builder->description ?? null;

        $discord = $this->modularDiscord->discord;
        $discordCommand = new Command($discord, $builder->toArray());

        $guild = null;
        if ($saved = (!$cacheCommand or !$this->isCommandCached($name)))
            (($guild = $command->getGuild()) != null ? $discord->guilds[$guild] : $discord->application)->commands->save($discordCommand, $saveReason);
        if ($cacheCommand and $saved)
            $this->modularDiscord->cache->cache(null, Cache::COMMANDS, [$name]);

        $discord->listenCommand(
            $name,
            fn (Interaction $i) => $command->onCommand($i, $i->data->options),
            fn (Interaction $i) => $command->onAutoComplete($i, $i->data->options)
        );

        $this->module->logger->info("Command $name successfully registered!", array_filter([
            'class' => get_class($command),
            'description' => $description,
            'reason' => $saveReason,
            'cached' => $cacheCommand,
            'guild' => $guild ?? null,
            'global' => $guild == null,
            'saved' => $saved
        ], fn ($v) => !is_null($v)));
    }

    public function getListeners(): array
    {
        return $this->listeners;
    }
}