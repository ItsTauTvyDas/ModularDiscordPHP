<?php

use Discord\Builders\CommandBuilder;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Repository\Interaction\OptionRepository;
use ModularDiscord\Base\AbstractCommand;
use Discord\Parts\Interactions\Command\Option;

class TestCommand extends AbstractCommand
{
    public function onCreate(): CommandBuilder
    {
        return CommandBuilder::new()
            ->addOption((new Option($this->discord))
                ->setName("user")
                ->setType(Option::USER)
                ->setRequired(true)
                ->setDescription("Select a user to greet")
            )
            ->setDmPermission(false);
    }

    public function onCommand(Interaction $interaction, OptionRepository $args): void
    {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent("Hello <@{$args->first()->value}>!"));
    }
}