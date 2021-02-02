<?php

namespace App\Exception;

class SendDataException extends \Exception
{
    /**
     * SendDataException constructor.
     *
     * @param array $exceptions
     */
    public function __construct(array $exceptions)
    {
        $message = "";
        $lastException = null;
        $repeatedException = null;
        $attemptsStart = 1;
        $repeatCount = 1;

        foreach ($exceptions as $key => $exception) {
            if (!$lastException || $exception->getMessage() !== $lastException->getMessage()) {
                if ($repeatedException) {
                    $this->getAttempString($message, $attemptsStart, $key, $repeatedException, $repeatCount);
                    $repeatCount = 1;
                }
                $repeatedException = $exception;
                $attemptsStart = $key + 1;
            } else {
                $repeatCount++;
            }

            $lastException = $exception;
        }

        $this->getAttempString($message, $attemptsStart, count($exceptions), $repeatedException, $repeatCount);

        parent::__construct($message);
    }

    /**
     * @param $message
     * @param $attemptsStart
     * @param $attemptsEnd
     * @param $repeatedException
     * @param $repeatCount
     */
    private function getAttempString(&$message, $attemptsStart, $attemptsEnd, $repeatedException, $repeatCount)
    {
        $errorMessage = explode('response', $repeatedException->getMessage())[0];

        if ($repeatCount > 1) {
            $message .= "Attempts $attemptsStart-$attemptsEnd: " . $errorMessage . "\n";
        } else {
            $message .= "Attempt $attemptsStart: " . $errorMessage . "\n";
        }
    }
}
