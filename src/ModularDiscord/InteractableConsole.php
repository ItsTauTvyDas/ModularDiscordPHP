<?php

namespace ModularDiscord;

use Error;
use Exception;
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
                    $callable = self::$registry[$name];
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

    public static function handleDefaultCommand(ModularDiscord $modDiscord, string $name, array $args)
    {
        switch ($name) {
            case 'stop':
            case 'close':
            case 'end':
                $modDiscord->close();
                break;
            case 'forceexit':
                exit(-1);
                break;
            default:
                $modDiscord->discord->getLogger()->info("Invalid command: $name");
        }
    }

    private static function isRunningWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
}