<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\GamePlayer;
use App\Entity\User;
use App\Exception\ApiException;
use App\Repository\GamePlayerRepository;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class MoveApplicationService
{
    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly GamePlayerRepository $gamePlayerRepository,
        private readonly GameEngine $gameEngine,
        private readonly MercurePublisher $mercurePublisher,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return array{game: Game, me: GamePlayer, opponent: ?GamePlayer, result: array}
     */
    public function play(string $gameId, User $user, int $value, string $suit, ?int $clientStateVersion = null): array
    {
        $game = $this->gameRepository->findWithFullRelations($gameId);
        if (!$game) {
            throw new ApiException(
                'game_not_found',
                'Partie introuvable.',
                Response::HTTP_NOT_FOUND
            );
        }

        $me = $this->gamePlayerRepository->findByGameAndUser($game, $user);
        if (!$me) {
            throw new ApiException(
                'game_access_denied',
                'Accès refusé.',
                Response::HTTP_FORBIDDEN
            );
        }

        if ($clientStateVersion !== null && $clientStateVersion !== $game->getStateVersion()) {
            throw new ApiException(
                'out_of_sync',
                'État client désynchronisé. Effectuez une resynchronisation.',
                Response::HTTP_CONFLICT,
                ['serverVersion' => $game->getStateVersion()]
            );
        }

        $result = $this->gameEngine->playCard($game, $user, $value, $suit);
        if ($result['status'] === 'error') {
            throw new ApiException(
                'rule_violation',
                $result['error'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $game->bumpStateVersion();
        $this->em->flush();

        match ($result['status']) {
            'waiting' => $this->mercurePublisher->publishCardPlayed($game, $user, $value, $suit),
            'round_complete' => $this->mercurePublisher->publishRoundComplete($game, $result),
            'finished' => $this->mercurePublisher->publishGameFinished($game, $result),
            default => null,
        };

        return [
            'game' => $game,
            'me' => $me,
            'opponent' => $this->gamePlayerRepository->findOpponent($game, $user),
            'result' => $result,
        ];
    }
}
