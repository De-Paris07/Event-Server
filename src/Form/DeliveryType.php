<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class DeliveryType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('type', ChoiceType::class, [
                'choices'  =>  Client::DELIVERY_TYPES,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('address', TextType::class, [
                'constraints' => [
                    new Length(['max' => 255]),
                    new NotBlank(),
                ],
            ])
            ->add('default', CheckboxType::class, []);
    }
}
