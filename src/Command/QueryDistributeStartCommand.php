<?php

namespace App\Command;

/**
 * Class QueryDistributeStartCommand
 *
 * @package App\Command
 */
class QueryDistributeStartCommand extends AbstractClientCommand
{
    protected static $defaultName = 'event-query:start';

    /**
     * @return string
     */
    protected function getTube(): string
    {
        return $_ENV['INPUT_QUERY_QUEUE'];
    }

    /**
     * @return string | null
     */
    protected function getStartMessage(): ?string
    {
        return 'Start query events to clients';
    }

    protected function configure()
    {
        $this->setDescription('Start service of distributing query to clients');
    }
}
