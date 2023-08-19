<h1>ModularDiscordPHP</h1>

[![Status](https://img.shields.io/badge/status-active-success.svg)]()
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](/LICENSE)

This is an extension for <a about = "_blank" href = "https://github.com/discord-php/DiscordPHP">DiscordPHP</a> which allows user to make modules that could control listeners or commands and this makes the code more organized. I have made something like this long ago but I just wanted to rewrite it because the code was horrible.

I made this so I could make bots easier and so I decided to public it.

## Features
- **Console/terminal support** - an ability to run commands (and register your own ones) through console! For now Windows are not supported! Not sure about other systems, I only tested this on Windows and Linux.
- **Modules** - the core thingy. They can also be disabled and enabled if needed.
- **Listeners** - make listeners be in separate files, define methods instead of calling them.
- **Commands** - easily register and listen to commands! Commands are only sent do discord once.
- **Accessors** - those so called "accessors" (I couldn't come up with anything better lol) are instances that every module can access. This is useful if you have an instance of one object and you want to access it in multiple modules.
- **Reloadable modules!** - you can also completely reload a module, meaning file changes are applied. This basically disabled current module, gets its code, renames the class and loads it again. Sadly, there is no other way of unloading a class in PHP. This might be useful for testing! **Note**: Other external files don't get reloaded.

## Getting Started

### Installing
```
composer require itstautvydas/modular-discord-php
```
### Basic usage
You can view the full example in [here](example).

```php
include 'vendor/autoload.php';

use Discord\Discord;
use ModularDiscord\ModularDiscord;

ModularDiscord::new()->loadAccessors()->loadModules()->initiateDiscord([
    'token' => '******'
    // Put your bot options here
], function (Discord $discord) {
    // Do something before running discord bot client...
})->run();
```

### About accessors and modules
Accessors do not require to have their own folder, but if they do have, the main file should be called `accessor.php` with a class name that is the same as folder's name.

Depending on other module is possible but just note that the modules get loaded alphabetically.
