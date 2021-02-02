<?php

namespace App\Consumer;

use App\Job\Job;

/**
 * Interface Consumer
 *
 * @package App\Consumer
 */
interface Consumer
{
    /**
     * @param Job $job
     *
     * @return void
     */
    public function consume(Job $job);
}
