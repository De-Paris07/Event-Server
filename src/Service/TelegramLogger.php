<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TelegramLogger
{
    /** @var ContainerInterface $container */
    private $container;

    /** @var string $eventId */
    private $eventId = null;

    /** @var string $eventName */
    private $eventName = null;

    /** @var string $chatId */
    private $chatId;

    /** @var boolean $useProxy */
    private $useProxy;

    /** @var ClientInterface $client */
    private $guzzleClient;

    /**
     * TelegramLogger constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->guzzleClient = new Client();
        $this->useProxy = filter_var($container->getParameter('use_proxy'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $this->chatId = $container->getParameter('prod' === $this->container->getParameter('kernel.environment') ? 'chat_id' : 'chat_id_dev');
    }

    /**
     * @param bool $useProxy
     *
     * @return $this
     */
    public function setUseProxy(bool $useProxy): TelegramLogger
    {
        $this->useProxy = $useProxy;

        return $this;
    }

    /**
     * @param string $eventId
     * @param string | null $eventName
     */
    public function setCurrentEvent(string $eventId, string $eventName = null)
    {
        $this->eventId = $eventId;
        $this->eventName = $eventName;
    }

    /**
     * @param $exception
     * @param string|null $clientMessage
     */
    public function setFail($exception, string $clientMessage = null)
    {
        $serviceName = $this->container->getParameter('service_name');
        $currentEnvironment = $this->container->getParameter('kernel.environment');
        $environments = $this->container->getParameter('environments');
        $message = '';

        if (!in_array($currentEnvironment, $environments)) {
            return;
        }

        if ('prod' === $currentEnvironment) {
            $message = "‼️‼️ALERT‼️‼️\n";
        }

        $message = $message . "Environment $currentEnvironment \n" . (!is_null($this->eventId) ? "EventId: {$this->eventId} \n" : '') . (!is_null($this->eventName) ? "EventName: {$this->eventName} \n" : '') . "Service: $serviceName \n";

        if (!is_null($clientMessage)) {
            $message = $message . $clientMessage;
        }

        $message = $message . "Message: {$exception->getMessage()} \n \n";
        $trace = stristr($exception->getTraceAsString(), '#3', true);

        $message = $message . $trace;

        $this->write($message);
    }

    /**
     * @param string | array $message
     */
    public function log($message)
    {
        $serviceName = $this->container->getParameter('service_name');
        $currentEnvironment = $this->container->getParameter('kernel.environment');
        $info = "Service: $serviceName \n";

        if ('prod' === $currentEnvironment) {
            return;
        }

        if (!is_null($this->eventName) && !is_null($this->eventId)) {
            $info .= "EventId: {$this->eventId} \nEventName: {$this->eventName} \n";
        }
        
        if (is_array($message)) {
            $message = json_encode($message, 128 | 256);
        }

        if (is_object($message)) {
            throw new \RuntimeException('To write a message to the log, a string or array is expected, an object is transferred.');
        }

        $this->write("{$info}MessageLog: \n $message");
    }

    /**
     * @param string $message
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function write(string $message)
    {
        $proxy = $this->container->getParameter('socks5');
        $token = $this->container->getParameter('token');

        if ($this->useProxy && (is_null($proxy) || '' === $proxy)) {
            return;
        }

        if (is_null($this->chatId) || is_null($token) || '' === $token || '' === $this->chatId) {
            return;
        }

        $options = [
            'body' => json_encode(['chat_id' => $this->chatId, 'text' => $message]),
            'headers' => ['Content-Type' => 'application/json'],
        ];

        if ($this->useProxy) {
            $options['proxy'] = 'socks5://' . $proxy;
        }

        try {
            $response = $this->guzzleClient->request('POST', "https://api.telegram.org/$token/sendMessage", $options);

            if (!is_null($response)) {
                echo "Message sent to telegram chat '$this->chatId'" . PHP_EOL;
            }
        } catch (\Throwable $exception){
            echo "Error message sent to telegram chat '$this->chatId'" . $exception->getMessage();
        }
    }
}
