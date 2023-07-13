<?php

namespace ModularDiscord;

use Error;
use Exception;
use Seld\Signal\SignalHandler;

class InteractableConsole
{
    private static array $registry = [];
    private readonly bool $loaded;

    public static function registerCommand(string $name, callable $function)
    {
        $registry[$name] = $function;
    }

    public static function listenForCommands(ModularDiscord $modDiscord)
    {
        if (isset(self::$loaded) and self::$loaded)
            return;
        self::$loaded = true;
        $discord = $modDiscord->discord;
        if (self::isRunningWindows())
            return $discord->getLogger()->warning("Failed to initialize console command listener due to 'stream_set_blocking' function not being supported on Windows.");

        $discord->getLogger()->info('Listening for console commands...');
        $stdin = fopen('php://stdin', 'r');
        if (stream_set_blocking($stdin, false))
        {
            $discord->getLoop()->addPeriodicTimer(1, function () use ($stdin, $modDiscord, $discord) {
                try {
                    $line = fgets($stdin);
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

    /**
     * Control + C handler.
     */
    public static function handleSignals(ModularDiscord $modDiscord)
    {
        $signal = SignalHandler::create([SignalHandler::SIGINT]); //Listen for CTRL + C

        $modDiscord->discord->getLoop()->addPeriodicTimer(0.1, function () use (&$signal, $modDiscord) {
            if ($signal->isTriggered()) {
                $signal->reset();
                echo PHP_EOL;
                $modDiscord->close();
                $signal->exitWithLastSignal();
            }
        });
    }

    public static function handleDefaultCommand(ModularDiscord $modDiscord, string $name, array $args)
    {
        switch ($name) {
            case 'stop':
            case 'close':
            case 'end':
            case 'exit':
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