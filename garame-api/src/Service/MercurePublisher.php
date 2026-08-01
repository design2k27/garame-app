<?php
// src/Service/MercurePublisher.php

namespace App\Service;

use App\Entity\Game;
use App\Entity\User;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {}

    private function gameTopic(Game $game): string
    {
        return sprintf('game/%s', $game->getId());
    }

    public function publishGameStarted(Game $game, array $result): void
    {
        $this->publish($game, [
            'event'   => 'game_started',
            'eventId' => $game->getStateVersion(),
            'stateVersion' => $game->getStateVersion(),
            'status'  => $result['status'],
            'winner'  => isset($result['winner']) ? $result['winner']->getUsername() : null,
            'winType' => isset($result['winType']) ? $result['winType']->value : null,
            'leader'  => $game->getCurrentLeader()?->getUsername(),
        ]);
    }

    public function publishCardPlayed(Game $game, User $user, int $value, string $suit): void
    {
        $this->publish($game, [
            'event'  => 'card_played',
            'eventId' => $game->getStateVersion(),
            'stateVersion' => $game->getStateVersion(),
            'player' => $user->getUsername(),
            'card'   => ['value' => $value, 'suit' => $suit],
            'leader' => $game->getCurrentLeader()?->getUsername(),
        ]);
    }

    public function publishRoundComplete(Game $game, array $result): void
    {
        $this->publish($game, [
            'event'       => 'round_complete',
            'eventId' => $game->getStateVersion(),
            'stateVersion' => $game->getStateVersion(),
            'roundWinner' => $result['roundWinner']->getUsername(),
            'nextLeader'  => $game->getCurrentLeader()?->getUsername(),
            'roundNumber' => $game->getCurrentRound(),
        ]);
    }

    public function publishGameFinished(Game $game, array $result): void
    {
        $this->publish($game, [
            'event'   => 'game_finished',
            'eventId' => $game->getStateVersion(),
            'stateVersion' => $game->getStateVersion(),
            'winner'  => $result['winner']->getUsername(),
            'winType' => $result['winType']->value,
        ]);
    }

    private function publish(Game $game, array $data): void
    {
        try {
            $update = new Update(
                $this->gameTopic($game),
                json_encode($data)
            );
            $this->hub->publish($update);
        } catch (\Throwable $e) {
            // Mercure non disponible en dev — on ignore silencieusement
        }
    }
}
