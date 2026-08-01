<?php
// src/Controller/GameController.php

namespace App\Controller;

use App\Entity\User;
use App\Exception\ApiException;
use App\Service\GameApplicationService;
use App\Service\GamePayloadBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/games', name: 'api_game_')]
class GameController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly GameApplicationService $gameApplicationService,
        private readonly GamePayloadBuilder $payloadBuilder,
    ) {}

    #[Route('/open', name: 'open', methods: ['GET'])]
    public function openGames(): JsonResponse
    {
        $games = $this->gameApplicationService->openGames();

        return $this->json([
            'action' => 'listed',
            'games' => array_map(fn($g) => $this->payloadBuilder->buildSummary($g), $games),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $result = $this->gameApplicationService->create($user);
        } catch (ApiException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getDetails());
        }

        return $this->json(
            $this->payloadBuilder->buildSingleGameResponse(
                $result['action'],
                $this->payloadBuilder->buildSummary($result['game']),
                $this->payloadBuilder->buildResult($result['game'], $result['result'])
            ),
            $result['statusCode']
        );
    }

    #[Route('/matchmaking', name: 'matchmaking', methods: ['POST'])]
    public function matchmaking(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $result = $this->gameApplicationService->matchmaking($user);
        } catch (ApiException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getDetails());
        }

        return $this->json(
            $this->payloadBuilder->buildSingleGameResponse(
                $result['action'],
                $this->payloadBuilder->buildSummary($result['game']),
                $this->payloadBuilder->buildResult($result['game'], $result['result'])
            ),
            $result['statusCode']
        );
    }

    #[Route('/matchmaking/cancel', name: 'matchmaking_cancel', methods: ['POST'])]
    public function cancelMatchmaking(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $result = $this->gameApplicationService->cancelMatchmaking($user);

        return $this->json([
            'action' => $result['action'],
            'gameId' => $result['gameId'],
        ], $result['statusCode']);
    }

    #[Route('/my/active', name: 'my_active', methods: ['GET'])]
    public function myActive(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $games = $this->gameApplicationService->activeGamesForUser($user);

        return $this->json([
            'action' => 'listed',
            'games' => array_map(fn($g) => $this->payloadBuilder->buildSummary($g), $games),
        ]);
    }

    #[Route('/my/history', name: 'my_history', methods: ['GET'])]
    public function myHistory(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = filter_var($request->query->get('page', 1), FILTER_VALIDATE_INT);
        $perPage = filter_var($request->query->get('perPage', 10), FILTER_VALIDATE_INT);

        if ($page === false || $page < 1) {
            return $this->errorResponse(
                'invalid_page',
                'Le paramètre page doit être un entier supérieur ou égal à 1.',
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($perPage === false || $perPage < 1 || $perPage > 50) {
            return $this->errorResponse(
                'invalid_per_page',
                'Le paramètre perPage doit être un entier entre 1 et 50.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $history = $this->gameApplicationService->finishedGamesForUser($user, $page, $perPage);

        return $this->json([
            'action' => 'listed',
            'items' => array_map(
                fn($g) => $this->payloadBuilder->buildHistoryItem($g, $user),
                $history['games']
            ),
            'pagination' => $history['pagination'],
        ]);
    }

    #[Route('/rejoin', name: 'rejoin', methods: ['GET'])]
    public function rejoin(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $result = $this->gameApplicationService->rejoin($user);
        if ($result['game'] === null || $result['me'] === null) {
            return $this->json([
                'action' => 'none',
                'game' => null,
                'result' => null,
            ]);
        }

        return $this->json(
            $this->payloadBuilder->buildSingleGameResponse(
                'rejoined',
                $this->payloadBuilder->buildFull($result['game'], $result['me'], $result['opponent']),
                $this->payloadBuilder->buildResult($result['game'])
            )
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], priority: -1)]
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $result = $this->gameApplicationService->show($id, $user);
        } catch (ApiException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getDetails());
        }

        return $this->json(
            $this->payloadBuilder->buildSingleGameResponse(
                'fetched',
                $this->payloadBuilder->buildFull($result['game'], $result['me'], $result['opponent']),
                $this->payloadBuilder->buildResult($result['game'])
            )
        );
    }

    #[Route('/{id}/sync', name: 'sync', methods: ['GET'], priority: -1)]
    public function sync(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $sinceVersionRaw = $request->query->get('sinceVersion');
        $sinceVersion = null;

        if ($sinceVersionRaw !== null && $sinceVersionRaw !== '') {
            $sinceVersion = filter_var($sinceVersionRaw, FILTER_VALIDATE_INT);
            if ($sinceVersion === false || $sinceVersion < 1) {
                return $this->errorResponse(
                    'invalid_state_version',
                    'Le paramètre sinceVersion doit être un entier supérieur ou égal à 1.',
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        try {
            $result = $this->gameApplicationService->sync($id, $user, $sinceVersion);
        } catch (ApiException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getDetails());
        }

        return $this->json([
            'action' => 'synced',
            'inSync' => $result['inSync'],
            'serverVersion' => $result['serverVersion'],
            'game' => $this->payloadBuilder->buildFull($result['game'], $result['me'], $result['opponent']),
            'result' => $this->payloadBuilder->buildResult($result['game']),
        ]);
    }

    #[Route('/{id}/poll', name: 'poll', methods: ['GET'], priority: -1)]
    public function poll(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $sinceVersion = filter_var($request->query->get('sinceVersion', 0), FILTER_VALIDATE_INT);
        if ($sinceVersion === false || $sinceVersion < 0) {
            return $this->errorResponse(
                'invalid_state_version',
                'Le paramètre sinceVersion doit être un entier supérieur ou égal à 0.',
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $result = $this->gameApplicationService->poll($id, $user, $sinceVersion);
        } catch (ApiException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getDetails());
        }

        if (!$result['changed']) {
            return $this->json([
                'action' => 'polled',
                'changed' => false,
                'serverVersion' => $result['serverVersion'],
                'game' => null,
                'result' => null,
                'pollAfterMs' => $result['pollAfterMs'],
            ]);
        }

        return $this->json([
            'action' => 'polled',
            'changed' => true,
            'serverVersion' => $result['serverVersion'],
            'game' => $this->payloadBuilder->buildFull($result['game'], $result['me'], $result['opponent']),
            'result' => $this->payloadBuilder->buildResult($result['game']),
            'pollAfterMs' => $result['pollAfterMs'],
        ]);
    }

    #[Route('/{id}/ack', name: 'ack', methods: ['POST'], priority: -1)]
    public function ack(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->errorResponse(
                'invalid_json',
                'JSON invalide.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $stateVersion = $data['stateVersion'] ?? null;
        if (!is_int($stateVersion) || $stateVersion < 1) {
            return $this->errorResponse(
                'invalid_state_version',
                'stateVersion doit être un entier supérieur ou égal à 1.',
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $result = $this->gameApplicationService->acknowledgeState($id, $user, $stateVersion);
        } catch (ApiException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getDetails());
        }

        return $this->json([
            'action' => $result['action'],
            'gameId' => $result['gameId'],
            'ackedStateVersion' => $result['ackedStateVersion'],
            'serverVersion' => $result['serverVersion'],
            'inSync' => $result['inSync'],
        ], $result['statusCode']);
    }

    #[Route('/{id}/rematch', name: 'rematch', methods: ['POST'], priority: -1)]
    public function rematch(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $result = $this->gameApplicationService->rematch($id, $user);
        } catch (ApiException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getDetails());
        }

        if ($result['game'] === null || $result['me'] === null) {
            return $this->json([
                'action' => $result['action'],
                'status' => $result['status'],
                'originalGameId' => $result['originalGameId'],
                'game' => null,
                'result' => null,
            ], $result['statusCode']);
        }

        return $this->json([
            'action' => $result['action'],
            'status' => $result['status'],
            'originalGameId' => $result['originalGameId'],
            'game' => $this->payloadBuilder->buildFull($result['game'], $result['me'], $result['opponent']),
            'result' => $this->payloadBuilder->buildResult($result['game'], $result['result']),
        ], $result['statusCode']);
    }

    #[Route('/{id}/join', name: 'join', methods: ['POST'], priority: -1)]
    public function join(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $result = $this->gameApplicationService->join($id, $user);
        } catch (ApiException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getDetails());
        }

        return $this->json(
            $this->payloadBuilder->buildSingleGameResponse(
                $result['action'],
                $this->payloadBuilder->buildSummary($result['game']),
                $this->payloadBuilder->buildResult($result['game'], $result['result'])
            ),
            $result['statusCode']
        );
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'], priority: -1)]
    public function cancel(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $result = $this->gameApplicationService->cancel($id, $user);
        } catch (ApiException $e) {
            return $this->errorResponse($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getDetails());
        }

        return $this->json([
            'action' => $result['action'],
            'gameId' => $result['gameId'],
        ], $result['statusCode']);
    }
}
