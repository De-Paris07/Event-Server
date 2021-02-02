<?php

namespace App\Job;

/**
 * Class EventJob
 *
 * @package App\Job
 */
class EventJob extends BaseJob
{
    /**
     * @return boolean
     */
    public function isHistoryEvent()
    {
        return $this->data['history'];
    }
}
