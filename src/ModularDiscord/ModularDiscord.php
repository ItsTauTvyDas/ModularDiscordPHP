<?php

namespace ModularDiscord;

use DateTimeZone;
use Discord\Discord;
use Error;
use Exception;
use ModularDiscord\Base\Module;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use ParseError;
use Psr\Log\LoggerInterface;

class ModularDiscord
{
    public readonly array $settings;
    public readonly Discord $discord;
    public readonly LoggerInterface $logger;
    public readonly Cache $cache;

    private array $modules = [];

    private bool $closing = false;

    /**
     * Makes a new instance of ModularDiscord
     * @param SettingsBuilder|array|false $settings (Optional) Settings.
     */
    public static function new(Settings|array|callable|null $settings = null): ModularDiscord
    {
        $i = new ModularDiscord();

        if ($settings === null)
            $i->settings = (new Settings())->settings;
        elseif (is_callable($settings))
            $i->settings = $settings(new Settings())->settings;
        elseif ($settings instanceof Settings)
            $i->settings = $settings->settings;
        else
            $i->settings = $settings;

        foreach (array_values($i->settings['folders']) as $folder)
            @mkdir($folder);
        $i->logger = $i->createLogger('ModularDiscord');
        $i->cache = new Cache($i->settings['cache']['filename']);
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
     * So called "accessors" are the type of module that can be accessed by every module.
     * Useful if you have some kind of API with one instance that you need to access in multiple modules.
     * @return self
     */
    public function loadAccessors(): self
    {
        // TODO
        return $this;
    }

    /**
     * Forcefully loads already loaded module with modified contents.
     * This basically gets module's code, renames the class then it evaluates code directly.
     * Renaming class in PHP is impossible and you can't load a class that is already loaded.
     * This was done just for easier testing.
     * When adding to global modules array, original name is used and new name is used only when initializing the class.
     * Be sure to restart bot later as this can accumulate some useless memory!
     * Note: This does not 'reload' other module's files.
     */
    public function refreshModuleFile(string $name, &$newName = null): bool
    {
        $module = @$this->modules[$name] ?? null;
        if ($module == null)
            return false;
        $newName = str_replace('.', '', $name.microtime(true));
        $module->setEnabled(false);
        $moduleCode = file_get_contents($module->path);
        $moduleCode = str_replace(" $name ", " $newName ", $moduleCode);
        if (str_starts_with($moduleCode, '<?php'))
            $moduleCode = str_replace('<?php', '', $moduleCode);
        elseif (str_starts_with($moduleCode, '<?'))
            $moduleCode = str_replace('<?', '', $moduleCode);
        try {
            eval($moduleCode);
            $this->loadModule($module->path, $newName, $name, $module->registry);
            return true;
        } catch (Exception | Error | ParseError $ex) {
            $module->logger->error("Fatal error: Caught ".get_class($ex).": Failed to refresh and reload $name module: " . $ex->getMessage());
            $module->logger->error($ex->getTraceAsString());
            return false;
        }
    }

    /**
     * Reload a module (disable and enable again).
     */
    public function reloadModule(string $name)
    {
        $module = $this->modules[$name] ?? null;
        if ($module != null) {
            $module->setEnabled(false);
            $module->setEnabled(true);
        }
    }

    private function loadModule(string $path, string $name, string $displayName = null, ?Registry $registry = null): ?Module
    {
        $firstLoad = $displayName == null;
        if ($displayName == null)
            $displayName = $name;

        try {
            $instance = new $name($displayName, $path, $this);
            if ($firstLoad)
                $instance->loadLocalFiles();
            if ($registry != null) {
                $instance->registry->registeredCommands = $registry->registeredCommands;
            }
            $instance->onEnable(true);
            $instance->logger->info('Module loaded and enabled!');
            if (isset($this->discord) and $instance->callReadyOnEnable)
                $instance->onDiscordReady($this->discord);
            $this->modules[$displayName] = $instance;
            return $instance;
        } catch (Exception | Error $ex) {
            $this->logger->error("Failed to load $displayName module: " . $ex->getMessage());
            $this->logger->error($ex->getTraceAsString());
            return null;
        }
    }

    /**
     * Load modules.
     * @return self
     */
    public function loadModules(): self
    {
        $this->logger->info('Loading modules...');
        foreach (glob($this->settings['folders']['modules'] . '/*/module.php') as $moduleFile) {
            require_once $moduleFile;
            $name = pathinfo($moduleFile, PATHINFO_DIRNAME);
            $name = substr($name, strrpos($name, '/') + 1);
            if (class_exists($name)) {
                $this->loadModule($moduleFile, $name);
                continue;
            }
            $this->logger->warning("Failed to load $moduleFile module: Class $moduleFile does not exist!", [
                'file' => $moduleFile
            ]);
        }
        return $this;
    }

    /**
     * Execute function for every module (if it exists).
     */
    public function executeGlobalModuleFunction(string $function, array $params = [])
    {
        foreach (array_values($this->modules) as $module)
            if (method_exists($module, $function) and $module->isEnabled())
                $module->$function(...$params);
    }

    /**
     * Disable all modules and close discord instance.
     */
    public function close()
    {
        $this->closing = true;
        if (isset($this->discord))
            $this->discord->close();
    }

    /**
     * Check if the bot is being closed (not forcefully)
     */
    public function isBeingClosedByUser(): bool
    {
        return $this->closing;
    }

    /**
     * Initiate discord bot client and run it.
     * @param array $options Discord bot's options.
     * @param Discord $discord Discord reference.
     * @return self
     */
    public function initiateDiscord(array $options, callable|null $callable = null): self
    {
        $this->logger->info('Initiating and starting up Discord engine...');
        $this->discord = ($discord = new Discord(array_merge_recursive($options, ['logger' => $this->createLogger('DiscordPHP')])));
        if ($callable != null)
            $callable($discord);
        $this->executeGlobalModuleFunction('onDiscordInit', [$discord]);

        $discord->on('ready', function (Discord $discord) {
            $this->executeGlobalModuleFunction('onDiscordReady', [$discord]);
        });

        if ($this->settings['console']['commands'])
            InteractableConsole::listenForCommands($this);
        if ($this->settings['console']['handle-ctrl-c'])
            InteractableConsole::handleSignals($this);

        return $this;
    }

    /**
     * Run discord loop.
     * Any code after this function may not get executed.
     */
    public function run()
    {
        $this->discord->run();
        InteractableConsole::closeConsoleStream();
        $this->executeGlobalModuleFunction('onDisable');
        $this->executeGlobalModuleFunction('onClose');
        $this->logger->info('Fully closed!');
    }
}