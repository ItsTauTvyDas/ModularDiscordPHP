<?php

namespace ModularDiscord;

use Psr\Log\LoggerInterface;

abstract class Accessor
{
    public readonly LoggerInterface $logger;
    public abstract function get(): mixed;

    public function __construct(ModularDiscord $modularDiscord)
    {
        $this->logger = $modularDiscord->createLogger('Accessor#' . get_class($this));
    }

    public function close() {}
}