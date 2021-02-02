<?php

namespace App\Command;

/**
 * Class EventsDistributeStartCommand
 *
 * @package App\Command
 */
class EventsDistributeStartCommand extends AbstractClientCommand
{
    protected static $defaultName = 'events-distribute:start';

    /**
     * @return string
     */
    protected function getTube(): string
    {
        return $_ENV['INPUT_EVENT_QUEUE'];
    }

    /**
     * @return string | null
     */
    protected function getStartMessage(): ?string
    {
        return 'Start distributing events to clients';
    }

    protected function configure()
    {
        $this->setDescription('Start service of distributing events to clients');
    }
}
