<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ProfileStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profile', name: 'api_profile_')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly ProfileStatsService $profileStatsService,
    ) {}

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'action' => 'fetched',
            'stats' => $this->profileStatsService->buildForUser($user),
        ]);
    }
}
