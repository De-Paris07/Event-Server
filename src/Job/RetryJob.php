<?php

declare(strict_types=1);

namespace App\Job;

/**
 * Class RetryJob
 *
 * @package App\Job
 */
class RetryJob extends BaseJob
{
    /**
     * @return string | null
     */
    public function getRetryEventId(): ?string
    {
        return $this->data['retryEventId'] ?? null;
    }

    /**
     * @return int
     */
    public function getRetryPriority(): int
    {
        return $this->data['retryPriority'];
    }
}
