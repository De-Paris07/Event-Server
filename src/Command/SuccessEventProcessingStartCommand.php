<?php

namespace App\Command;

/**
 * Class SuccessEventProcessingStartCommand
 *
 * @package App\Command
 */
class SuccessEventProcessingStartCommand extends AbstractClientCommand
{
    protected static $defaultName = 'success-event-processing:start';

    /**
     * @return string
     */
    protected function getTube(): string
    {
        return $_ENV['SUCCESS_QUEUE'];
    }

    /**
     * @return string | null
     */
    protected function getStartMessage(): ?string
    {
        return 'Start processing jobs with success answer from client';
    }

    protected function configure()
    {
        $this->setDescription('Processing jobs with success answer from client');
    }
}
