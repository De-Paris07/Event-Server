<?php

namespace App\Exception;

use RuntimeException;

class ConnectTimeoutException extends RuntimeException
{
    /**
     * @var integer
     */
    private $timeout;

    /**
     * ConnectTimeoutException constructor.
     *
     * @param $timeout
     * @param null $message
     * @param null $code
     * @param null $previous
     */
    public function __construct(int $timeout, $message = null, $code = null, $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->timeout = $timeout;
    }

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }
}
