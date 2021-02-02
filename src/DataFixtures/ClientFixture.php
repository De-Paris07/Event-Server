<?php

namespace App\DataFixtures;

use App\Entity\Client;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClientFixture extends BaseFixture implements FixtureGroupInterface
{
    public $uniqueField = 'name';
    public $created = true;

    const DATA = [
        [
            'id' => '1e4ba449-61e7-11e9-b763-40b0760fb018',
            'name' => 'aster.test',
            'delivery_type' => 3,
            'delivery_address' => '',
            'client_token' => '',
            'server_token' => '3b24f28eb59f7dcc688c155072f1150235cc65h7ca5c8e7a5ebdf8c8cd13e069',
            'success_address' => '',
            'fail_address' => '',
        ],
        [
            'id' => '5e81dd5f-c6d5-41a8-b307-5aa15174a676',
            'name' => 'aster',
            'delivery_type' => 3,
            'delivery_address' => '',
            'client_token' => '',
            'server_token' => '7e42718dcb258c89da1a38bb6345769f2e4c131fc06f99a8580ed1b362f58f68',
            'success_address' => '',
            'fail_address' => '',
        ],
    ];

    /**
     * OAuthClientFixture constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->entityClass = Client::class;
        $this->tableName = 'client';
        $this->data = self::DATA;
    }

    /**
     * @return array
     */
    public static function getGroups(): array
    {
        return ['group1'];
    }
}
