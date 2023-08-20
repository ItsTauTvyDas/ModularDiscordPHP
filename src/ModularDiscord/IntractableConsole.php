<?php

namespace ModularDiscord;

use Error;
use Exception;
use ParseError;
use Seld\Signal\SignalHandler;

class IntractableConsole
{
    private static array $registry = [];
    private static bool $loaded = false;
    private static mixed $stdin = null;

    /**
     * @param string $name Name of the command
     * @param callable $function Executed on command call,
     */
    public static function registerCommand(string $name, callable $function): void
    {
        self::$registry[$name] = $function;
    }

    public static function listenForCommands(ModularDiscord $modDiscord): void
    {
        if (self::$loaded)
            return;
        self::$loaded = true;
        $discord = $modDiscord->discord;

        if (self::isRunningWindows()) {
            $discord->getLogger()->warning("Failed to initialize console command listener due to 'stream_set_blocking' function not being supported on Windows.");
            return;
        }

        $discord->getLogger()->info('Listening for console commands...');
        if (stream_set_blocking(STDIN, false))
        {
            $discord->getLoop()->addPeriodicTimer(1, function () use ($modDiscord, $discord) {
                try {
                    $line = fgets(self::$stdin);
                    $split = explode(' ', $line);
                    $name = strtolower($split[0]);
                    $args = [];
                    if (count($split) > 1)
                        $args = array_slice($split, 1);
                    self::handleCommand($modDiscord, $name, $args);
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

    public static function closeConsoleStream(): void
    {
        if (self::$stdin != null)
            fclose(self::$stdin);
    }

    /**
     * CTRL + C handler.
     */
    public static function handleSignals(ModularDiscord $modDiscord): void
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

    private static function handleCommand(ModularDiscord $modDiscord, string $name, array $args): void
    {
        $log = $modDiscord->discord->getLogger();
        switch ($name) {
            case 'stop':
            case 'close':
            case 'end':
                $modDiscord->close();
                break;
            case 'help':
                $log->info('fetch');
                $log->info('uptime');
                $log->info('stop/close/end');
                $log->info('forceexit');
                $log->info('reloadmodule/rlmod <name>');
                $log->info('callaccessor <name>');
                $log->info('callmodule <name>');
                $log->info('refreshmodule/rfmod <name>');
                break;
            case 'refreshmodule':
            case 'rfmod':
                if (isset($args[0])) {
                    if (!$modDiscord->refreshModuleFile($args[0], $newNameRef)) {
                        if (!isset($newNameRef))
                            $log->error("Module '$args[0]' doesn't exist!");
                        break;
                    }
                    $log->info("Module '$args[0]' successfully refreshed/reloaded!");
                    break;
                }
                $log->error('Missing args');
                break;
            case 'reloadmodule':
            case 'rlmod':
                if (isset($args[0])) {
                    if ($modDiscord->reloadModule($args[0])) {
                        $log->info("Module '$args[0]' reloaded successfully.");
                        break;
                    }
                    $log->error("Module '$args[0]' doesn't exist!");
                    break;
                }
                $log->error('Missing args');
                break;
            case 'callaccessor':
            case 'callmodule':
                $type = substr($name, 4);
                if (isset($args[0])) {
                    if (($mod = $modDiscord->$type($args[0])) != null) {
                        $function = $args[1] ?? 'consoleCall';
                        if (!method_exists($mod, $function)) {
                            $log->error("Function '$function' doesn't exist!");
                            break;
                        }
                        try {
                            $array = array_slice($args, 2);
                            $mod->$function($array);
                        } catch (Exception | Error | ParseError $e) {
                            $modDiscord->handleException($e, "Got an exception while invoking \$$type->$function() !");
                        }
                        break;
                    }
                    $log->info(ucfirst($type) . " '$args[0]' doesn't exist!");
                    break;
                }
                $log->error('Missing args');
                break;
            case 'fetch':
                switch ($args[0] ?? null) {
                    default:
                        $log->info('> fetch command <name/id>:<[name/id]> <[method/field]/print/methods/fields>');
                        $log->info('> fetch <user/guild/member> <[value]> <[method/field]/print/methods/fields>');
                }
                break;
            case 'die':
                exit(-1);
            default:
                $callable = self::$registry[$name] ?? null;
                if ($callable != null) {
                    $callable($modDiscord, $name, $args);
                    return;
                }
                $log->error("Invalid command: $name");
        }
    }

    private static function isRunningWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
}