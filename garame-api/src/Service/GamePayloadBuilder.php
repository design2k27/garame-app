<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\GamePlayer;
use App\Entity\GameResult;
use App\Entity\User;

class GamePayloadBuilder
{
    public function buildSummary(Game $game): array
    {
        $players = array_values($game->getPlayers()->map(fn($gp) => [
            'id'       => $gp->getUser()->getId(),
            'username' => $gp->getUser()->getUsername(),
            'credits'  => $gp->getUser()->getCredits(),
            'position' => $gp->getPosition(),
        ])->toArray());

        return [
            'id'            => $game->getId(),
            'status'        => $game->getStatus()->value,
            'stateVersion'  => $game->getStateVersion(),
            'currentRound'  => $game->getCurrentRound(),
            'currentLeader' => $game->getCurrentLeader()?->getUsername(),
            'players'       => $players,
            'startedAt'     => $game->getStartedAt()->format(\DateTimeInterface::ATOM),
            'endedAt'       => $game->getEndedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function buildFull(Game $game, GamePlayer $me, ?GamePlayer $opponent): array
    {
        $completedRounds = $game->getRounds()->filter(
            fn($r) => $r->getWinner() !== null
        );

        $currentRound = $game->getRounds()->filter(
            fn($r) => $r->getWinner() === null
        )->first() ?: null;

        $currentRoundData = null;
        if ($currentRound) {
            $currentRoundData = [
                'number' => $currentRound->getNumber(),
                'leader' => $currentRound->getLeader()->getUsername(),
                'moves'  => array_values($currentRound->getMoves()->map(fn($m) => [
                    'player'    => $m->getUser()->getUsername(),
                    'card'      => ['value' => $m->getCardValue(), 'suit' => $m->getCardSuit()],
                    'playOrder' => $m->getPlayOrder(),
                ])->toArray()),
            ];
        }

        $rounds = array_values($completedRounds->map(fn($r) => [
            'number'  => $r->getNumber(),
            'leader'  => $r->getLeader()->getUsername(),
            'winner'  => $r->getWinner()?->getUsername(),
            'winType' => $r->getWinType()?->value,
            'moves'   => array_values($r->getMoves()->map(fn($m) => [
                'player'    => $m->getUser()->getUsername(),
                'card'      => ['value' => $m->getCardValue(), 'suit' => $m->getCardSuit()],
                'playOrder' => $m->getPlayOrder(),
            ])->toArray()),
        ])->toArray());

        return [
            'id'               => $game->getId(),
            'status'           => $game->getStatus()->value,
            'stateVersion'     => $game->getStateVersion(),
            'currentRound'     => $game->getCurrentRound(),
            'currentLeader'    => $game->getCurrentLeader()?->getUsername(),
            'winType'          => $game->getWinType()?->value,
            'winner'           => $game->getWinner()?->getUsername(),
            'myHand'           => $me->getHand(),
            'myTricksWon'      => $me->getTricksWon(),
            'myAckedStateVersion' => $me->getLastAckedStateVersion(),
            'opponent'         => $opponent ? [
                'id'        => $opponent->getUser()->getId(),
                'username'  => $opponent->getUser()->getUsername(),
                'credits'   => $opponent->getUser()->getCredits(),
                'tricksWon' => $opponent->getTricksWon(),
                'cardsLeft' => count($opponent->getHand()),
                'ackedStateVersion' => $opponent->getLastAckedStateVersion(),
            ] : null,
            'currentRoundData' => $currentRoundData,
            'rounds'           => $rounds,
            'startedAt'        => $game->getStartedAt()->format(\DateTimeInterface::ATOM),
            'endedAt'          => $game->getEndedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function buildResult(Game $game, ?array $engineResult = null): array
    {
        $winner = $engineResult['winner'] ?? $game->getWinner();
        $winType = $engineResult['winType'] ?? $game->getWinType();
        $roundWinner = $engineResult['roundWinner'] ?? null;
        $nextLeader = $engineResult['nextLeader'] ?? null;
        $nextRound = $engineResult['nextRound'] ?? null;

        return [
            'status' => $engineResult['status'] ?? $game->getStatus()->value,
            'winner' => $winner instanceof User ? $winner->getUsername() : null,
            'winType' => $winType?->value,
            'roundWinner' => $roundWinner instanceof User ? $roundWinner->getUsername() : null,
            'nextLeader' => $nextLeader instanceof User ? $nextLeader->getUsername() : null,
            'nextRound' => is_int($nextRound) ? $nextRound : null,
        ];
    }

    public function buildSingleGameResponse(
        string $action,
        array $gamePayload,
        array $resultPayload
    ): array {
        return [
            'action' => $action,
            'game' => $gamePayload,
            'result' => $resultPayload,
        ];
    }

    public function buildHistoryItem(Game $game, User $viewer): array
    {
        $result = $game->getResult();
        $winner = $result?->getWinner();
        $loser = $result?->getLoser();
        $opponent = null;

        foreach ($game->getPlayers() as $player) {
            if ($player->getUser()->getId() !== $viewer->getId()) {
                $opponent = $player->getUser();
                break;
            }
        }

        return [
            'id' => $game->getId(),
            'status' => $game->getStatus()->value,
            'startedAt' => $game->getStartedAt()->format(\DateTimeInterface::ATOM),
            'endedAt' => $game->getEndedAt()?->format(\DateTimeInterface::ATOM),
            'winType' => $game->getWinType()?->value,
            'didWin' => $winner?->getId() === $viewer->getId(),
            'opponent' => $opponent ? [
                'id' => $opponent->getId(),
                'username' => $opponent->getUsername(),
                'credits' => $opponent->getCredits(),
            ] : null,
            'result' => $result ? $this->buildHistoryResult($result) : null,
        ];
    }

    private function buildHistoryResult(GameResult $result): array
    {
        return [
            'winner' => $result->getWinner()->getUsername(),
            'loser' => $result->getLoser()->getUsername(),
            'winType' => $result->getWinType()->value,
            'stakeMultiplier' => $result->getStakeMultiplier(),
            'createdAt' => $result->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
