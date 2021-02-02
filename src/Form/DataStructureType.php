<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class DataStructureType extends AbstractType
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
            ->add('constraints', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_extra_fields' => true,
                'allow_add' => true
            ]);
    }
}