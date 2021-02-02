<?php

namespace App\Handler;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;

class ClientHandler
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * ClientHandler constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Create client
     *
     * @param array $data
     * @return Client
     * @throws \Exception
     */
    public function create(array $data)
    {
        $client = new Client();

        $this->setData($client, $data);

        $this->em->persist($client);
        $this->em->flush();

        return $client;
    }

    /**
     * Update data of client
     *
     * @param Client $client Client for updating
     * @param array $data New data for client
     * @return Client Updated client
     */
    public function update(Client &$client, array $data = [])
    {
        if (count($data)) {
            $this->setData($client, $data);
        }

        $this->em->persist($client);
        $this->em->flush();

        return $client;
    }

    /**
     * Set data for client
     *
     * @param Client $client Client
     * @param array $data Data for client
     */
    private function setData(Client &$client, array $data)
    {
        $client->setName($data['name']);

        foreach ($data['delivery'] as $delivery) {
            if ($delivery['type'] === Client::DELIVERY_TYPES[Client::DELIVERY_TYPE_SOCKET]) {
                $client->setSocketUri($delivery['address']);
            }

            if (!$delivery['default']) {
               continue;
            }

            $deliveryType = Client::getDeliveryTypeByName($delivery['type']);
            $client->setDeliveryType($deliveryType);
            $client->setDeliveryAddress($delivery['address']);
        }

        $client->setReceiveHistoricalData($data['receiveHistoricalData']);
        $client->setFailAddress($data['callbackFailUrl']);
        $client->setSuccessAddress($data['callbackSuccessUrl']);
    }
}
