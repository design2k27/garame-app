<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\WinType;
use App\Repository\GameResultRepository;

class ProfileStatsService
{
    private const BASE_CREDITS = 10;

    public function __construct(
        private readonly GameResultRepository $gameResultRepository,
    ) {}

    public function buildForUser(User $user): array
    {
        $base = $this->gameResultRepository->getStatsForUser($user);
        $totalGames = $base['wins'] + $base['losses'];
        $winRate = $totalGames > 0
            ? round(($base['wins'] / $totalGames) * 100, 2)
            : 0.0;

        $byWinType = $this->buildByWinType($user);
        $recent = $this->buildRecent($user);
        $streak = $this->buildCurrentStreak($recent);

        return [
            'summary' => [
                'totalGames' => $totalGames,
                'wins' => $base['wins'],
                'losses' => $base['losses'],
                'winRate' => $winRate,
                'credits' => $user->getCredits(),
                'gamesPlayed' => $user->getGamesPlayed(),
                'gamesWon' => $user->getGamesWon(),
            ],
            'byWinType' => $byWinType,
            'streak' => $streak,
            'recent' => $recent,
        ];
    }

    private function buildByWinType(User $user): array
    {
        $aggregate = $this->gameResultRepository->aggregateByWinTypeForUser($user);
        $indexed = [];
        foreach ($aggregate as $row) {
            $winType = $row['winType'] instanceof WinType
                ? $row['winType']->value
                : (string) $row['winType'];

            $indexed[$winType] = [
                'wins' => (int) $row['wins'],
                'losses' => (int) $row['losses'],
            ];
        }

        $output = [];
        foreach (WinType::cases() as $winType) {
            $wins = $indexed[$winType->value]['wins'] ?? 0;
            $losses = $indexed[$winType->value]['losses'] ?? 0;
            $output[$winType->value] = [
                'wins' => $wins,
                'losses' => $losses,
                'total' => $wins + $losses,
            ];
        }

        return $output;
    }

    private function buildRecent(User $user): array
    {
        $results = $this->gameResultRepository->findRecentForUser($user, 20);
        $recent = [];

        foreach ($results as $result) {
            $didWin = $result->getWinner()->getId() === $user->getId();
            $opponent = $didWin ? $result->getLoser() : $result->getWinner();
            $delta = self::BASE_CREDITS * $result->getStakeMultiplier();

            $recent[] = [
                'gameId' => $result->getGame()->getId(),
                'resultId' => $result->getId(),
                'didWin' => $didWin,
                'winType' => $result->getWinType()->value,
                'stakeMultiplier' => $result->getStakeMultiplier(),
                'creditsDelta' => $didWin ? $delta : -$delta,
                'opponent' => [
                    'id' => $opponent->getId(),
                    'username' => $opponent->getUsername(),
                ],
                'playedAt' => $result->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return $recent;
    }

    private function buildCurrentStreak(array $recent): array
    {
        if ($recent === []) {
            return ['type' => 'none', 'count' => 0];
        }

        $first = (bool) $recent[0]['didWin'];
        $count = 0;

        foreach ($recent as $item) {
            if ((bool) $item['didWin'] !== $first) {
                break;
            }
            $count++;
        }

        return [
            'type' => $first ? 'win' : 'loss',
            'count' => $count,
        ];
    }
}
