<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ranking', name: 'api_ranking_')]
class RankingController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $users = $this->userRepository->findLeaderboard(20);

        return $this->json([
            'action' => 'listed',
            'items' => array_map(
                static fn($user, int $index) => [
                    'rank' => $index + 1,
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'credits' => $user->getCredits(),
                    'gamesPlayed' => $user->getGamesPlayed(),
                    'gamesWon' => $user->getGamesWon(),
                ],
                $users,
                array_keys($users)
            ),
        ], Response::HTTP_OK);
    }
}
