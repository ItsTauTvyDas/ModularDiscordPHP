<?php

namespace ModularDiscord\Base;

use Discord\Discord;
use ModularDiscord\ModularDiscord;

abstract class Command
{
    public abstract function onCommand(Discord $discord, ModularDiscord $modularDiscord);
}