<?php

namespace App\Exception;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ValidateFormException extends HttpException
{
    public $errors;

    /**
     * ValidateException constructor.
     *
     * @param FormInterface $form
     */
    public function __construct(FormInterface $form)
    {
        $this->errors = $this->getErrorsFromForm($form);
        parent::__construct(Response::HTTP_BAD_REQUEST, 'Form validate error(s)');
    }

    /**
     * Returns form errors.
     *
     * @param FormInterface $form
     * @return array
     */
    private function getErrorsFromForm(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors() as $key => $error) {
            $template = $error->getMessageTemplate();
            $parameters = $error->getMessageParameters();
            foreach ($parameters as $var => $value) {
                $template = str_replace($var, $value, $template);
            }
            $errors[$key] = $template;
        }
        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childErrors = $this->getErrorsFromForm($childForm)) {
                    $errors[$childForm->getName()] = $childErrors;
                }
            }
        }

        return $errors;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
