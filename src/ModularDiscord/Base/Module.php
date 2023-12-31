<?php

namespace ModularDiscord\Base;

use Discord\Discord;
use Exception;
use ModularDiscord\Base\Accessor;
use ModularDiscord\ModularDiscord;
use ModularDiscord\Registry;
use Psr\Log\LoggerInterface;

class Module
{
    public readonly ModularDiscord $modularDiscord;
    public readonly LoggerInterface $logger;
    public readonly Registry $registry;
    public readonly string $name, $path;
    private bool $disabled = false;

    /**
     * Useful when reloading a module.
     * This calls onDiscordReady when enabling (not on first load) the module.
     */
    public bool $callReadyOnEnable = false;
    /**
     * Make it true if you wish to cache listeners and unregister them later.
     */
    public bool $cacheListeners = false;

    /**
     * @throws Exception Thrown if failed to create logger.
     */
    public function __construct(string $name, string $path, ModularDiscord $modularDiscord)
    {
        $this->name = $name;
        $this->modularDiscord = $modularDiscord;
        $this->path = $path;
        $this->logger = $modularDiscord->createLogger('Module#'.$name);
        $this->registry = new Registry($this);
    }

    public function onEnable() {}
    public function onDisable() {}
    public function onClose() {}
    public function onAccessorReady(Accessor $accessor) {}
    public function onDiscordInit(Discord $discord) {}
    public function onDiscordReady(Discord $discord) {}
    public function consoleCall(string ...$params) {}

    /**
     * Executed once on first load (after class initialization).
     */
    public function loadLocalFiles() {}

    /**
     * Only works in onEnable() and ($callReadyOnEnable is true) onDiscordReady() methods.
     * @see Module::onEnable()
     * @see Module::onDiscordReady()
     * @see Module::$callReadyOnEnable
     */
    public final function isFirstTimeLoad(): bool
    {
        return !isset($this->modularDiscord->getModules()[$this->name]);
    }

    /**
     * Note that if false, discord listeners are removed.
     * @param bool $bool
     */
    public final function setEnabled(bool $bool): void
    {
        $this->disabled = !$bool;
        if ($bool) {
            $this->onEnable();
        } else {
            $this->onDisable();
            $this->registry->removeDiscordListeners();
        }
    }

    public final function isEnabled(): bool
    {
        return !$this->disabled;
    }
}