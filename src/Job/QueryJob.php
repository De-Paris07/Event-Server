<?php

namespace App\Job;

/**
 * Class QueryJob
 *
 * @package App\Job
 */
class QueryJob extends BaseJob
{
    /**
     * @return string
     */
    public function getRoute()
    {
        return $this->data['route'];
    }
}
