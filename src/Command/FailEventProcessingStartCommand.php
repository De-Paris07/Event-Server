<?php

namespace App\Command;

/**
 * Class FailEventProcessingStartCommand
 *
 * @package App\Command
 */
class FailEventProcessingStartCommand extends AbstractClientCommand
{
    protected static $defaultName = 'fail-event-processing:start';

    /**
     * @return string
     */
    protected function getTube(): string
    {
        return $_ENV['FAIL_QUEUE'];
    }

    /**
     * @return string | null
     */
    protected function getStartMessage(): ?string
    {
        return 'Start processing jobs with fail answer from client';
    }

    protected function configure()
    {
        $this->setDescription('Processing jobs with fail answer from client');
    }
}
