<?php

namespace App\Job;

/**
 * Class FailEventProcessingJob
 *
 * @package App\Job
 */
class FailEventProcessingJob extends AnswerJob
{
    /**
     * @return string
     */
    public function getError()
    {
        return $this->data['error'];
    }
}
