<?php

namespace App\Service;

use App\Entity\Client;
use App\Handler\ClientHandler;
use App\Repository\ClientRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ClientAuthService
{
    const CLIENT_TOKEN_NAME = 'Client-Token';
    const SERVER_TOKEN_NAME = 'Server-Token';

    /**
     * @var RequestStack
     */
    protected $request;

    /**
     * @var ClientRepository
     */
    private $clientRepository;

    /**
     * @var Client
     */
    private $client = null;

    /**
     * @var ClientHandler
     */
    private $clientHandler;

    /**
     * ClientAuthService constructor.
     *
     * @param RequestStack $requestStack
     * @param ClientRepository $clientRepository
     * @param ClientHandler $clientHandler
     */
    public function __construct(
        RequestStack $requestStack,
        ClientRepository $clientRepository,
        ClientHandler $clientHandler
    ) {
        $this->request = $requestStack->getCurrentRequest();
        $this->clientRepository = $clientRepository;
        $this->clientHandler = $clientHandler;
    }

    /**
     * @return Client|null
     */
    public function getClientByToken()
    {
        $serverToken = $this->getServerToken();

        $client = $this->clientRepository->findOneBy(['serverToken' => $serverToken]);
        $this->client = $client;

        return $client;
    }

    /**
     * @return Client | null
     */
    public function getCurrentClient()
    {
        return $this->client;
    }

    /**
     * @return null|string
     */
    private function getServerToken(): ?string
    {
        return $this->request->headers->get(ClientAuthService::SERVER_TOKEN_NAME);
    }

    /**
     * @return bool
     */
    public function isCurrentClientValid()
    {
        return !is_null($this->getClientByToken());
    }

    /**
     * Auth client check
     *
     * @param Client $client
     * @return bool
     */
    public function checkClient(Client $client)
    {
        return !is_null($client) && $client->getServerToken() === $this->getServerToken();
    }

    /**
     * @param Response $response
     * @param Client|null $client
     */
    public function applyClientTokenToResponse(Response &$response, Client $client = null)
    {
        if (is_null($client)) {
            $client = $this->getCurrentClient();
        }
        $response->headers->set(ClientAuthService::CLIENT_TOKEN_NAME, $client->getClientToken());
    }

    /**
     * @param Response $response
     * @param Client|null $client
     */
    public function applyServerTokenToResponse(Response &$response, Client $client = null)
    {
        if (is_null($client)) {
            $client = $this->getCurrentClient();
        }
        $response->headers->set(ClientAuthService::SERVER_TOKEN_NAME, $client->getServerToken());
    }

    /**
     * @param Client $client
     *
     * @throws \Exception
     */
    public function setupAuthForClient(Client $client)
    {
        $serverToken = bin2hex(random_bytes(32));
        $client->setServerToken($serverToken);

        if (!$this->request->headers->has(ClientAuthService::CLIENT_TOKEN_NAME)) {
            throw new BadRequestHttpException('No set client token in request');
        }
        $clientToken = $this->request->headers->get(ClientAuthService::CLIENT_TOKEN_NAME);
        $client->setClientToken($clientToken);

        $this->clientHandler->update($client);
    }
}
