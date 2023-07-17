<?php

namespace ModularDiscord;

use Closure;
use Discord\Helpers\RegisteredCommand;
use Discord\Parts\Interactions\Command\Command;
use Discord\Parts\Interactions\Interaction;
use ModularDiscord\Base\AbstractCommand;
use ModularDiscord\Base\Listener;
use ModularDiscord\Base\Module;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;

final class Registry
{
    private readonly ModularDiscord $modularDiscord;
    private readonly Module $module;
    /**
     * @var array<string, array<Closure>> $listeners
     */
    public array $listeners = [];
    /**
     * @var array<string, array<string>> $registeredCommands
     */
    public array $registeredCommands = [];

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

    public function registerCommand(AbstractCommand $command, ?string $name = null, ?string $description = null, ?string $guild = null, bool $cacheCommand = true, ?string $saveReason = null): ?RegisteredCommand
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
        $guild = $guild ?? $command->getGuild();

        if ($guild != null and !$discord->guilds->has($guild)) {
            $this->module->logger->error("Couldn't register '$name' command. Guild $guild does not exist!");
            return null;
        }

        $commands = ($guild != null ? $discord->guilds[$guild] : $discord->application)->commands;
        
        if ($saved = (!$cacheCommand or !$this->isCommandCached($name)))
            $commands->save($discordCommand, $saveReason);
        if ($cacheCommand and $saved)
            $this->modularDiscord->cache->cache(null, Cache::COMMANDS, [$name]);

        if (!isset($this->registeredCommands[$guild ?? 'global']))
            $this->registeredCommands[$guild ?? 'global'] = [];
        $commandRegistry = &$this->registeredCommands[$guild ?? 'global'];
        if (!in_array($name, $commandRegistry)) {
            $commandRegistry[] = $name;

            $registered = $discord->listenCommand(
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
            return $registered;
        }
        return null;
    }

    public function unregisterAllCommands()
    {
        foreach ($this->registeredCommands as $guild => $commandNames) {
            $guild != 'global' or $guild = null;
            foreach ($commandNames as $name)
                self::unregisterCommand($this->modularDiscord, $name, $guild, logger: $this->module->logger);
        }
    }

    public static function unregisterCommand(ModularDiscord $modularDiscord, string $name, ?string $guild = null, ?string $reason = null, LoggerInterface $logger = null)
    {
        $discord = $modularDiscord->discord;
        $commands = ($guild != null ? $discord->guilds[$guild] : $discord->application)->commands;
        $logger = ($logger ?? $modularDiscord->logger);
        // TODO find a way to get command by name
        $commands->delete($name, $reason)->done(fn () => $logger->info("Unregistered $name command successfully", array_filter([
            'guild' => $guild,
            'global' => $guild == null,
            'reason' => $reason
        ], fn ($v) => !is_null($v))));
        $modularDiscord->cache->cache(null, Cache::COMMANDS, [$name], true);
    }
}