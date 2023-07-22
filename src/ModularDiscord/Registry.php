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
     * @var array<string, array<string, string>> $registeredCommands
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
        return $this->modularDiscord->cache->isCached(Cache::COMMANDS, $name);
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
            $this->module->logger->error("Couldn't register '$name' command as guild with an ID of '$guild' does not exist!");
            return null;
        }

        $commands = ($guild != null ? $discord->guilds[$guild] : $discord->application)->commands;
        
        $key = $guild ?? 'global';
        if (!isset($this->registeredCommands[$key]))
            $this->registeredCommands[$key] = [];
        $commandRegistry = &$this->registeredCommands[$key];

        if ($saved = (!$cacheCommand or !$this->isCommandCached($name))) {
            $commands->save($discordCommand, $saveReason)->done(function (Command $cmd) use ($name, $cacheCommand, &$commandRegistry) {
                if ($cacheCommand)
                    $this->modularDiscord->cache->cache(Cache::COMMANDS, $name, $cmd->id);
                $commandRegistry[$name] = $cmd->id;
            });
        }

        if (!in_array($name, $commandRegistry)) {
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
        foreach ($this->registeredCommands as $guild => $commands) {
            $guild != 'global' or $guild = null;
            foreach ($commands as $name => $id)
                self::unregisterCommand($this->modularDiscord, $name, $id, $guild, logger: $this->module->logger);
        }
    }

    public static function unregisterCommand(ModularDiscord $modularDiscord, string $name, string $id, ?string $guild = null, ?string $reason = null, LoggerInterface $logger = null)
    {
        $discord = $modularDiscord->discord;
        $commands = ($guild != null ? $discord->guilds[$guild] : $discord->application)->commands;
        $logger = ($logger ?? $modularDiscord->logger);
        $commands->delete($id, $reason)->done(fn () => $logger->info("Unregistered '$name' command successfully", array_filter([
            'guild' => $guild,
            'global' => $guild == null,
            'reason' => $reason
        ], fn ($v) => !is_null($v))));
        $modularDiscord->cache->cache(Cache::COMMANDS, $name, null);
    }
}