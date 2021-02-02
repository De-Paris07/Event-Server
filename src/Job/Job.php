<?php

namespace App\Job;

use App\Entity\Client;
use App\Entity\Event;

/**
 * Interface Job
 *
 * @package App\Job
 */
interface Job
{
    /**
     * @return string
     */
    public function getEventId(): string;

    /**
     * @return string | null
     */
    public function getEventName(): ?string;

    /**
     * @return Client | null
     */
    public function getClient(): ?Client;

    /**
     * @return Event | null
     */
    public function getEvent(): ?Event;

    /**
     * @param \Pheanstalk\Job $job
     */
    public function setJob(\Pheanstalk\Job $job): void;

    /**
     * @return string | null
     */
    public function getData(): ?string;

    /**
     * @param array $data
     */
    public function setData(array $data): void;
}
