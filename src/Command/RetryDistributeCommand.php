<?php

declare(strict_types=1);

namespace App\Command;

/**
 * Class RetryDistributeCommand
 *
 * @package App\Command
 */
class RetryDistributeCommand extends AbstractClientCommand
{
    protected static $defaultName = 'event-retry:start';

    /**
     * @return string
     */
    protected function getTube(): string
    {
        return $_ENV['INPUT_RETRY_QUEUE'];
    }

    /**
     * @return string | null
     */
    protected function getStartMessage(): ?string
    {
        return 'Start retry distributing events to clients';
    }

    protected function configure()
    {
        $this->setDescription('Start service of retry distributing');
    }
}
