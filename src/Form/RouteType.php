<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class RouteType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class, [
            'constraints' => [
                new Length(['min' => 3, 'max' => 255]),
                new NotBlank(),
                new NotNull(),
            ],
        ])
            ->add('description', TextType::class, [
                'constraints' => [
                    new Length(['min' => 3]),
                    new NotBlank(),
                    new NotNull(),
                ],
            ])
            ->add('validationScheme', TextType::class, [
                'constraints' => [

                ],
            ]);
    }
}
