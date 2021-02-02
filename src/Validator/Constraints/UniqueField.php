<?php

namespace App\Validator\Constraints;

use App\Validator\UniqueFieldValidator;
use Symfony\Component\Validator\Constraint;

class UniqueField extends Constraint
{
    public $message = 'Such record "%string%" already exists.';

    public $options;

    public function __construct($options = null)
    {
        $this->options = $options;
    }

    public function validatedBy()
    {
        return UniqueFieldValidator::class;
    }
}
