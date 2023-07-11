<?php

namespace ModularDiscord;

use Discord\Discord;
use Error;
use Exception;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

class InteractableConsole
{
    private static array $registry = [];

    public static function registerCommand(string $name, callable $function)
    {
        $registry[$name] = $function;
    }

    protected static function listenForCommands(ModularDiscord $modDiscord)
    {
        $discord = $modDiscord->discord();
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
                $modDiscord->discord()->getLogger()->info("Invalid command: $name");
        }
    }

    private static function isRunningWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
}