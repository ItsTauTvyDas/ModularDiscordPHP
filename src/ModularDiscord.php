<?php

namespace ModularDiscord;

use DateTimeZone;
use Discord\Discord;

class ModularDiscord
{

    private array $settings;
    private Discord $discord;

    private array $modules;

    /**
     * Makes a new instance of ModularDiscord
     * @param array|false $settings (Optional) Set or get settings.
     */
    public static function new(array|false &$settings = false): ModularDiscord
    {
        return new self($settings);
    }

    /**
     * Makes a new instance of ModularDiscord
     * @param array|false $settings (Optional) Set or get settings.
     */
    private function __construct(array|false &$settings = false)
    {
        if (!$settings)
            $this->settings = ($settings = $this->getDefaultSettings());
        else
            $this->settings = array_merge_recursive($this->getDefaultSettings(), $settings);
        foreach (array_values($this->settings['folders']) as $value)
            mkdir($value, recursive: true);
    }

    protected static function getDefaultSettings(): array
    {
        return [
            'folders' => [
                'modules' => 'Modules',
                'accessors' => 'Accessors'
            ],
            'logger' => [
                'time-zone' => 'Europe/Vilnius',
                'console-output' => 'php://stdout',
                'date-format' => 'Y/m/d H:i:s',
                'format' => '[%datetime%] %channel%.%level_name%: %message% %context% %extra%'
            ],
            'console' => [
                'handle-ctrl-c' => true
            ]
        ];
    }

    public function discord(): Discord
    {
        return $this->discord;
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function modulesFolder(string $folderName): self
    {
        $this->settings['folders']['modules'] = $folderName;
        return $this;
    }

    public function accessorsFolder(string $folderName): self
    {
        $this->settings['folders']['accessors'] = $folderName;
        return $this;
    }

    public function loggerTimeZone(DateTimeZone $timeZone): self
    {
        $this->settings['logger']['time-zone'] = $timeZone->getName();
        return $this;
    }

    public function loggerConsoleOutput(string $output): self
    {
        $this->settings['logger']['console-output'] = $output;
        return $this;
    }

    public function loggerDateFormat(string $format): self
    {
        $this->settings['logger']['date-format'] = $format;
        return $this;
    }

    public function loggerFormat(string $format): self
    {
        $this->settings['logger']['format'] = $format;
        return $this;
    }

    public function consoleHandleCtrlC(bool $bool): self
    {
        $this->settings['console']['handle-ctrl-c'] = $bool;
        return $this;
    }

    public function close()
    {
        
    }

    /**
     * Initiate discord bot client and run it.
     * @param array $options Discord bot's options.
     * @param Discord $discord Discord reference.
     * @return self
     */
    public function run(array|null $options = null, Discord &$discord = null): self
    {
        $this->discord = ($discord = new Discord($options ?? []));
        return $this;
    }
}