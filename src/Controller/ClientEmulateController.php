<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ClientEmulateController extends Controller
{
    /**
     * Endpoint for successing event processing result for client that sent event
     *
     * @Route("/success", methods={"POST"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function success(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        return $this->json([]);
    }

    /**
     * Endpoint for failing event processing result for client that sent event
     *
     * @Route("/fail", methods={"POST"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function fail(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        return $this->json([]);
    }
}
