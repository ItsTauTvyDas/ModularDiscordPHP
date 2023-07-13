<?php

namespace ModularDiscord;

use Discord\Discord;
use Error;
use Exception;

class ModularDiscord
{
    public readonly array $settings;
    public readonly Discord $discord;

    private array $modules;

    /**
     * Makes a new instance of ModularDiscord
     * @param SettingsBuilder|array|false $settings (Optional) Settings.
     */
    public static function new(Settings|array|null|callable $settings = null): ModularDiscord
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
            mkdir($folder);
        return $i;
    }

    private function __construct() {}

    public function getModules(): array
    {
        return $this->modules;
    }

    public function loadAccessors(): self
    {
        // TODO
        return $this;
    }

    public function loadModules(): self
    {
        foreach (glob($this->settings['folders']['modules'] . '/*/module.php') as $moduleFile) {
            include $moduleFile;
            $name = pathinfo($moduleFile, PATHINFO_DIRNAME);
            if (class_exists($name)) {
                try {
                    $instance = new $name();
                    $this->modules[$name] = $instance;
                } catch (Exception | Error $ex) {
                    $this->discord->getLogger()->error("Failed to load $moduleFile module: " . $ex->getMessage(), [
                        'line' => $ex->getLine(),
                        'file' => $ex->getFile()
                    ]);
                }
                continue;
            }
            $this->discord->getLogger()->warning("Failed to load $moduleFile module: Class $moduleFile does not exist!", [
                'file' => $moduleFile
            ]);
        }
        return $this;
    }

    /**
     * Execute function for every module (if it exists).
     */
    public function executeGlobalModuleFunction(string $function)
    {
        foreach (array_values($this->modules) as $module)
            if (method_exists($module, $function))
                $module->$function();
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
    public function run(array $options, Discord &$discord = null): self
    {
        if ($this->settings['console']['commands'])
            InteractableConsole::listenForCommands($this);

        if ($this->settings['console']['handle-ctrl-c'])
            InteractableConsole::handleSignals($this);

        $this->discord = ($discord = new Discord($options));
        return $this;
    }
}