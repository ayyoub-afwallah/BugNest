<?php

namespace App\Infrastructure\Adapter\Http\Controller\API\FeedBack;

use App\Application\useCase\CreateBugUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class FeedBackController
{
    public function __construct(private CreateBugUseCase $createBugUseCase)
    {
    }

    #[Route('/feed', name: 'app_feed')]
    public function index(Request $request): JsonResponse
    {
       $response =  $this->createBugUseCase->execute($request->request->all(), $request->files->get("image"));

       $response = new JsonResponse($response);
       $response->headers->set('Access-Control-Allow-Origin', '*');
       $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
       $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

       return $response;
    }
}
