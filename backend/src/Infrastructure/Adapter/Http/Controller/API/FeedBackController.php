<?php

namespace App\Infrastructure\Adapter\Http\Controller\API;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FeedBackController
{
    #[Route('/feedBack', name: 'app_feed_back', methods: ['POST'])]
    public function index(Request $request, CreateFeedbackUseCase $useCase): JsonResponse
    {
        $image = $request->files->get('image');
        $title = $request->request->get('title');
        $description = $request->request->get('description');

        if (!$image || !$title || !$description) {
            return new JsonResponse(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $feedback = $useCase->execute($title, $description, $image);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'message' => 'Feedback received',
            'feedback' => $feedback,
        ]);
    }
}
