<?php

namespace ModularDiscord\Base;

use Discord\Builders\CommandBuilder;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use Discord\Repository\Interaction\OptionRepository;
use ModularDiscord\ModularDiscord;

abstract class AbstractCommand
{
    public readonly ModularDiscord $modularDiscord;
    public readonly Discord $discord;
    public readonly Module $module;

    public function __construct(Module $module) {
        $this->module = $module;
        $this->modularDiscord = $module->modularDiscord;
        $this->discord = $module->modularDiscord->discord;
    }

    public abstract function onCreate(): CommandBuilder;
    public abstract function onCommand(Interaction $interaction, OptionRepository $args);
    public function onAutoComplete(Interaction $interaction, OptionRepository $args): array {
        return [];
    }
    public function getGuild(): ?string { return null; }
}