<?php

namespace ModularDiscord;

use DateTimeZone;
use Discord\Discord;
use Error;
use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class ModularDiscord
{
    public readonly array $settings;
    public readonly Discord $discord;
    public readonly LoggerInterface $globalLogger;

    private array $modules = [];

    /**
     * Makes a new instance of ModularDiscord
     * @param SettingsBuilder|array|false $settings (Optional) Settings.
     */
    public static function new(Settings|array|callable|null $settings = null): ModularDiscord
    {
        $i = new ModularDiscord();

        if ($settings === null)
            $i->settings = (new Settings())->settings;
        else if (is_callable($settings))
            $i->settings = $settings(new Settings())->settings;
        else if ($settings instanceof Settings)
            $i->settings = $settings->settings;
        else
            $i->settings = $settings;

        foreach (array_values($i->settings['folders']) as $folder)
            @mkdir($folder);
        $i->globalLogger = $i->createLogger('ModularDiscord');
        return $i;
    }

    public function createLogger(string $name): LoggerInterface
    {
        $logger = $this->settings['logger'];
        $formatter = new LineFormatter($logger['format'].PHP_EOL, $logger['date-format']);
        return new Logger($name, [
            (new StreamHandler($logger['console-output'], $logger['debug'] ? Level::Debug : Level::Info))->setFormatter($formatter)
        ], [], new DateTimeZone($logger['timezone']));
    }

    private function __construct() {}

    /**
     * Get loaded modules.
     * @return array<string, void> modules.
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Load accessors.
     * @return self
     */
    public function loadAccessors(): self
    {
        // TODO
        return $this;
    }

    /**
     * Load modules.
     * @return self
     */
    public function loadModules(): self
    {
        foreach (glob($this->settings['folders']['modules'] . '/*/module.php') as $moduleFile) {
            require_once $moduleFile;
            $name = pathinfo($moduleFile, PATHINFO_DIRNAME);
            $name = substr($name, strrpos($name, '/') + 1);
            echo $name.PHP_EOL;
            if (class_exists($name)) {
                try {
                    $instance = new $name($name, $this);

                    $this->modules[$name] = $instance;
                    $this->executeGlobalModuleFunction('onEnable');

                    $instance->logger->info("Module loaded and enabled!");
                } catch (Exception | Error $ex) {
                    $this->globalLogger->error("Failed to load $name module: " . $ex->getMessage(), [
                        'line' => $ex->getLine(),
                        'file' => $ex->getFile()
                    ]);
                }
                continue;
            }
            $this->globalLogger->warning("Failed to load $moduleFile module: Class $moduleFile does not exist!", [
                'file' => $moduleFile
            ]);
        }
        return $this;
    }

    /**
     * Execute function for every module (if it exists).
     */
    public function executeGlobalModuleFunction(string $function, ?mixed $param = null)
    {
        foreach (array_values($this->modules) as $module)
            if (method_exists($module, $function))
                $module->$function($param);
    }

    /**
     * Disable all modules and close discord instance.
     */
    public function close()
    {
        $this->executeGlobalModuleFunction('onDisable');
        $this->executeGlobalModuleFunction('onClose');
        if (isset($this->discord))
            $this->discord->close();
    }

    /**
     * Initiate discord bot client and run it.
     * @param array $options Discord bot's options.
     * @param Discord $discord Discord reference.
     * @return self
     */
    public function initiateDiscord(array $options, Discord &$discord = null): self
    {
        $this->discord = ($discord = new Discord($options));
        $this->executeGlobalModuleFunction('onDiscordInit', $discord);

        $discord->on('ready', function (Discord $discord) {
            $this->globalLogger->info('DONE! Client ready.');
            $this->executeGlobalModuleFunction('onDiscordReady', $discord);
        });

        if ($this->settings['console']['commands'])
            InteractableConsole::listenForCommands($this);
        if ($this->settings['console']['handle-ctrl-c'])
            InteractableConsole::handleSignals($this);
        return $this;
    }
}