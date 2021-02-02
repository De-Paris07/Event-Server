<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ObjectNotFoundException extends HttpException
{
    /**
     * ObjectNotFoundException constructor.
     * @param int $objectId Id for which was not found object
     */
    public function __construct($objectId)
    {
        $message = "Object with id $objectId non found";
        parent::__construct(Response::HTTP_BAD_REQUEST, $message);
    }
}
