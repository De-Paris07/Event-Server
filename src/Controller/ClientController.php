<?php

namespace App\Controller;

use App\Consumer\EventDistributeConsumer;
use App\Job\EventJob;
use App\Service\ClientAuthService;
use App\Service\ClientSubscribeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ClientController extends AbstractController
{
    /**
     * @Route("/test", name="test", methods={"GET"})
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function index()
    {
        return new JsonResponse(['message' => 'the service works.'], JsonResponse::HTTP_OK);
    }

    /**
     * @Route("/subscribe", name="subscribe_client", methods={"POST"})
     *
     * @param Request $request
     * @param ClientAuthService $authService
     * @param ClientSubscribeService $clientService
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function subscribe(
        Request $request,
        ClientAuthService $authService,
        ClientSubscribeService $clientService
    ) {
        $data = json_decode($request->getContent(), true);

        if ($client = $authService->getClientByToken()) {
            $clientService->updateSubscription($client, $data);

            return $this->json([]);
        }

        $client = $clientService->createSubscription($data);

        $response = new JsonResponse([]);
        $authService->applyServerTokenToResponse($response, $client);
        $authService->applyClientTokenToResponse($response, $client);

        return $response;
    }

    /**
     * @Route("/unsubscribe", name="unsubscribe client", methods={"POST"})
     *
     * @param Request $request
     * @param ClientSubscribeService $clientService
     *
     * @return Response
     */
    public function unsubscribe(Request $request, ClientSubscribeService $clientService)
    {
        $data = json_decode($request->getContent(), true);
        $clientService->unsubscribe($data);

        return new Response('Unsubscribe success');
    }

    /**
     * @Route("/event", name="new_event", methods={"POST"})
     *
     * @param Request $request
     * @param EventDistributeConsumer $distributeConsumer
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function eventAction(Request $request, EventDistributeConsumer $distributeConsumer)
    {
        $data = json_decode($request->getContent(), true);
        $eventName = $this->getParam($data, 'eventName');
        $event = $this->getParam($data, 'event');
        $serviceName = $this->getParam($data, 'serviceName');
        $data['eventId'] = md5($eventName . microtime() . bin2hex(random_bytes(5)));
        $data['history'] = false;

        if (is_array($event)) {
            $event['eventId'] = $data['eventId'];
            $event['senderServiceName'] = $serviceName;
            $event['eventName'] = $eventName;
            $event['created'] = (new \DateTime())->getTimestamp();
        }

        $data['event'] = serialize($event);

        $eventJob = new EventJob($this->getDoctrine()->getManager());
        $eventJob->setData($data);

        $distributeConsumer->consume($eventJob);

        return $this->json([]);
    }

    /**
     * @param array  $params
     * @param string $paramName
     * @param bool   $isCallException
     *
     * @return mixed
     */
    public static function getParam(array $params, string $paramName, bool $isCallException = true)
    {
        return !self::checkParam($params, $paramName, $isCallException) ?: $params[$paramName];
    }

    /**
     * @param array  $params
     * @param string $paramName
     * @param bool   $isCallException
     *
     * @return bool
     */
    public static function checkParam(array $params, string $paramName, bool $isCallException = true)
    {
        if (!isset($params[$paramName])) {
            if ($isCallException) {
                throw new BadRequestHttpException("Param '$paramName' non found");
            } else {
                return false;
            }
        } else {
            return true;
        }
    }
}
