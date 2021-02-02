<?php

namespace App\Form;

use App\Entity\Client;
use App\Service\ClientAuthService;
use App\Validator\Constraints\UniqueField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SubscribeType extends AbstractType
{
    /**
     * @var ClientAuthService
     */
    private $auth;

    /**
     * SubscribeType constructor.
     * @param ClientAuthService $auth
     */
    public function __construct(ClientAuthService $auth)
    {
        $this->auth = $auth;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $client = $this->auth->getCurrentClient();
        $clientId = ($client) ? $client->getId() : null;

        $builder->add('name', TextType::class, [
                'constraints' => [
                    new Length(['max' => 255]),
                    new NotBlank(),
                    new UniqueField(['entity' => Client::class, 'field' => 'name', 'id' => $clientId]),
                ],
            ])
            ->add('delivery', CollectionType::class, [
                'entry_type' => DeliveryType::class,
                'allow_extra_fields' => true,
                'allow_add' => true
            ])
            ->add('eventsSent', CollectionType::class, [
                'entry_type' => EventSentType::class,
                'allow_extra_fields' => true,
                'allow_add' => true
            ])
            ->add('eventsSubscribe', CollectionType::class, [
                'entry_type' => EventType::class,
                'allow_extra_fields' => true,
                'allow_add' => true
            ])
            ->add('routes', CollectionType::class, [
                'entry_type' => RouteType::class,
                'allow_extra_fields' => true,
                'allow_add' => true
            ])
            ->add('callbackFailUrl', TextType::class, [
                'constraints' => [
                    new Length(['max' => 255]),
                    new NotBlank(),
                ],
            ])
            ->add('callbackSuccessUrl', TextType::class, [
                'constraints' => [
                    new Length(['max' => 255]),
                    new NotBlank(),
                ],
            ])
            ->add('receiveHistoricalData', CheckboxType::class, []);
    }
}
