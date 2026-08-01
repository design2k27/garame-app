<?php
// src/Controller/MoveController.php

namespace App\Controller;

use App\Entity\User;
use App\Exception\ApiException;
use App\Service\GamePayloadBuilder;
use App\Service\MoveApplicationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/games', name: 'api_move_')]
class MoveController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly MoveApplicationService $moveApplicationService,
        private readonly GamePayloadBuilder $payloadBuilder,
    ) {}

    // -------------------------------------------------------------------------
    // POST /api/games/{id}/play
    // Jouer une carte
    // Body: { "value": 7, "suit": "H" }
    // -------------------------------------------------------------------------
    #[Route('/{id}/play', name: 'play', methods: ['POST'])]
    public function play(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Parser le body
        $data  = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->errorResponse(
                'invalid_json',
                'JSON invalide.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $value = $data['value'] ?? null;
        $suit  = $data['suit'] ?? null;
        $clientStateVersion = $data['clientStateVersion'] ?? null;

        if ($value === null || $suit === null) {
            return $this->errorResponse(
                'missing_card_payload',
                'Les champs value et suit sont obligatoires.',
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!is_int($value) || !is_string($suit)) {
            return $this->errorResponse(
                'invalid_card_payload',
                'value doit être un entier, suit une chaîne (H/D/C/S).',
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($clientStateVersion !== null && (!is_int($clientStateVersion) || $clientStateVersion < 1)) {
            return $this->errorResponse(
                'invalid_state_version',
                'clientStateVersion doit être un entier supérieur ou égal à 1.',
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $play = $this->moveApplicationService->play($id, $user, $value, $suit, $clientStateVersion);
        } catch (ApiException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getDetails());
        }

        return $this->json(
            $this->payloadBuilder->buildSingleGameResponse(
                'played',
                $this->payloadBuilder->buildFull($play['game'], $play['me'], $play['opponent']),
                $this->payloadBuilder->buildResult($play['game'], $play['result'])
            )
        );
    }
}
