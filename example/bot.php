<?php

include '../vendor/autoload.php';

use ModularDiscord\ModularDiscord;
use ModularDiscord\Settings;
use ModularDiscord\IntractableConsole;

IntractableConsole::registerCommand(
    "testcommand",
    fn (ModularDiscord $md) => $md->logger->info("Hello world!")
);

/** @noinspection PhpUnhandledExceptionInspection */
ModularDiscord::new(fn (Settings $s) => $s->debug(true))->loadAccessors()->loadModules()->initiateDiscord([
    'token' => 'your token here!'
])->run();