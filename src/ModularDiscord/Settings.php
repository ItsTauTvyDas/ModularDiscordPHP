<?php

namespace ModularDiscord;

use DateTimeZone;

class Settings
{
    public array $settings;
    
    public function __construct(array|null $presetArray = null)
    {
        if ($presetArray == null)
            $this->settings = self::getDefault();
        else
            $this->settings = array_merge_recursive($this->getDefault(), $presetArray);
    }

    public static function getDefault(): array
    {
        return [
            'folders' => [
                'modules' => 'Modules',
                'accessors' => 'Accessors'
            ],
            'logger' => [
                'timezone' => date_default_timezone_get(),
                'console-output' => 'php://stdout',
                'date-format' => 'Y/m/d H:i:s',
                'format' => '[%datetime%] %channel%.%level_name%: %message% %context% %extra%',
                'debug' => false
            ],
            'console' => [
                'commands' => true,
                'handle-ctrl-c' => true
            ]
        ];
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

    public function debug(bool $bool): self
    {
        $this->settings['console']['debug'] = $bool;
        return $this;
    }
}