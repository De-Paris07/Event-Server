<?php

namespace App\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UniqueFieldValidator extends ConstraintValidator
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * UniqueFieldValidator constructor.
     *
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param mixed $value
     * @param Constraint $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        $em = $this->em;
        $exist = $em->getRepository($constraint->options['entity'])->findOneBy([
            $constraint->options['field'] => $value,
        ]);
        if (isset($constraint->options['id']) && $constraint->options['id']) {
            if ($exist) {
                if ($exist->getId() == $constraint->options['id']) {
                    return;
                }
            }
        }

        if ($exist) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('%string%', $value)
                ->addViolation();
        }
    }
}
