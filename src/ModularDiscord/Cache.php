<?php

namespace ModularDiscord;

class Cache
{
    public readonly string $file;
    public array $source = [];

    public const COMMANDS = 'commands';

    public function __construct(string $file) {
        $this->file = $file;
        if (!file_exists($file))
            file_put_contents($file, '[]');
        $this->source = $this->readFile();
    }

    public function cache(?string $category, string $key, $value)
    {
        $array = &$this->source;
        if ($category != null) {
            if (!isset($array[$category]))
                $this->source[$category] = [];
            $array = &$this->source[$category];
        }

        if ($value == null) {
            unset($array[$key]);
            if ($category != null and !count($array))
                unset($this->source[$category]);
        } else {
            if (is_array($value)) {
                if (!isset($array[$key]) or !is_array($array[$key]))
                    $array[$key] = [];
                array_push($array[$key], ...$value);
            } else
                $array[$key] = $value;
        }
        $this->saveToFile($this->source);
    }

    private function readFile(): array
    {
        return json_decode(file_get_contents($this->file), true);
    }

    private function saveToFile(array $source)
    {
        file_put_contents($this->file, json_encode($source, JSON_PRETTY_PRINT));
    }

    public function getCached(?string $category, string $key, $default = null): mixed
    {
        $array = $this->source;
        if ($category != null) {
            if (!isset($array[$category]) or !is_array($array[$category]))
                return $default;
            $array = $array[$category];
        }
        return $array[$key] ?? $default;
    }

    public function isCached(?string $category, string $key): bool
    {
        return $this->getCached($category, $key) != null;
    }
}