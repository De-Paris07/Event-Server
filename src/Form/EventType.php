<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Class EventType
 *
 * @package App\Form
 */
class EventType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class, [
            'constraints' => [
                new Length(['max' => 255]),
                new NotBlank(),
            ],
        ])
            ->add('priority', TextType::class, [
                'constraints' => [
                    new Length(['max' => 10]),
                    new NotBlank(),
                ],
            ])
            ->add('channel', TextType::class, [
                'constraints' => [
                    new Length(['max' => 50]),
                ],
            ])
            ->add('servicePriority', TextType::class, [
                'constraints' => [
                    new Length(['max' => 50]),
                    new NotBlank(),
                ],
            ])
            ->add('isRetry', CheckboxType::class, [
                'constraints' => [
                    new NotNull(),
                ],
            ])
            ->add('countRetry', TextType::class, [
                'constraints' => [
                    new Length(['max' => 10]),
                    new NotBlank(),
                ],
            ])
            ->add('intervalRetry', TextType::class, [
                'constraints' => [
                    new Length(['max' => 10]),
                    new NotBlank(),
                ],
            ])
            ->add('priorityRetry', TextType::class, [
                'constraints' => [
                    new Length(['max' => 11]),
                    new NotBlank(),
                ],
            ]);
    }
}
