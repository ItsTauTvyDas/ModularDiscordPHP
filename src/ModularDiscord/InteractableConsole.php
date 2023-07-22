<?php

namespace ModularDiscord;

use Error;
use Exception;
use ParseError;
use Seld\Signal\SignalHandler;

class InteractableConsole
{
    private static array $registry = [];
    private static bool $loaded = false;
    private static mixed $stdin = null;

    public static function registerCommand(string $name, callable $function)
    {
        $registry[$name] = $function;
    }

    public static function listenForCommands(ModularDiscord $modDiscord)
    {
        if (self::$loaded)
            return;
        self::$loaded = true;
        $discord = $modDiscord->discord;
        if (self::isRunningWindows())
            return $discord->getLogger()->warning("Failed to initialize console command listener due to 'stream_set_blocking' function not being supported on Windows.");

        $discord->getLogger()->info('Listening for console commands...');
        self::$stdin = fopen(STDIN, 'r');
        if (stream_set_blocking(self::$stdin, false))
        {
            $discord->getLoop()->addPeriodicTimer(1, function () use ($modDiscord, $discord) {
                try {
                    $line = fgets(self::$stdin);
                    $split = explode(' ', $line);
                    $name = strtolower($split[0]);
                    $args = [];
                    if (count($split) > 1)
                        $args = array_slice($split, 1);
                    $callable = self::$registry[$name] ?? null;
                    if ($callable == null)
                        return self::handleDefaultCommand($modDiscord, $name, $args);
                    $callable($modDiscord, $name, $args);
                } catch (Exception | Error $ex) {
                    $discord->getLogger()->error("Error handling console command: {$ex->getMessage()}", [
                        'line' => $ex->getLine(),
                        'file' => $ex->getFile()
                    ]);
                }
            });
            return;
        }
        $discord->getLogger()->error('stream_set_blocking(...) failed: console input handler not initialized.');
    }

    public static function closeConsoleStream()
    {
        if (self::$stdin != null)
            fclose(self::$stdin);
    }

    /**
     * CTRL + C handler.
     */
    public static function handleSignals(ModularDiscord $modDiscord)
    {
        $signal = SignalHandler::create([SignalHandler::SIGINT]); //Listen for CTRL + C

        $modDiscord->discord->getLoop()->addPeriodicTimer(0.1, function () use (&$signal, $modDiscord) {
            if ($signal->isTriggered()) {
                $signal->reset();
                // If pressed second time, exit forcefully
                if ($modDiscord->isBeingClosedByUser())
                    $signal->exitWithLastSignal();
                else
                    $modDiscord->close();
            }
        });
    }

    private static function handleDefaultCommand(ModularDiscord $modDiscord, string $name, array $args)
    {
        $log = $modDiscord->discord->getLogger();
        switch ($name) {
            case 'stop':
            case 'close':
            case 'end':
                return$modDiscord->close();
                return;
            case 'help':
                $log->info('fetch');
                $log->info('uptime');
                $log->info('stop/close/end');
                $log->info('forceexit');
                $log->info('reloadmodule/rlmod <name>');
                $log->info('callaccessor <name>');
                $log->info('callmodule <name>');
                $log->info('refreshmodule/rfmod <name>');
                return;
            case 'refreshmodule':
            case 'rfmod':
                if (isset($args[0])) {
                    if (!$modDiscord->refreshModuleFile($args[0], $newNameRef)) {
                        if (!isset($newNameRef))
                            return $log->error("Module '{$args[0]}' doesn't exist!");
                        return;
                    }
                    return $log->info("Module '{$args[0]}' successfully refreshed/reloaded!");
                }
                return $log->error('Missing args');
            case 'reloadmodule':
            case 'rlmod':
                if (isset($args[0])) {
                    if ($modDiscord->reloadModule($args[0]))
                        return $log->info("Module '{$args[0]}' reloaded successfully.");
                    return $log->error("Module '{$args[0]}' doesn't exist!");
                }
                return $log->error('Missing args');
            case 'callaccessor':
            case 'callmodule':
                $type = substr($name, 4);
                if (isset($args[0])) {
                    if (($mod = $modDiscord->$type($args[0])) != null) {
                        $function = $args[1] ?? 'consoleCall';
                        if (!method_exists($mod, $function))
                            return $log->error("Function '$function' doesn't exist!");
                        try {
                        return $mod->$function();
                        } catch (Exception | Error | ParseError $e) {
                            $modDiscord->handleException($e, "Got an exception while invoking \${$type}->{$function}() !");
                        }
                        return;
                    }
                    return $log->info(ucfirst($type) . " '{$args[0]}' doesn't exist!");
                }
                return $log->error('Missing args');
            case 'callmodule':
            case 'fetch':
                switch ($args[0] ?? null) {
                    default:
                        $log->info('> fetch command <name/id>:<[name/id]> <[method/field]/print/methods/fields>');
                        $log->info('> fetch <user/guild/member> <[value]> <[method/field]/print/methods/fields>');
                }
                return;
            case 'forceexit':
                return exit(-1);
            default:
                return $log->error("Invalid command: $name");
        }
    }

    private static function isRunningWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
}