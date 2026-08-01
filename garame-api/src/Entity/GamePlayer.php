<?php
// src/Entity/GamePlayer.php

namespace App\Entity;

use App\Repository\GamePlayerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GamePlayerRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_player_per_game', columns: ['game_id', 'user_id'])]
class GamePlayer
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Game::class, inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'gamePlayers')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    // 1 = premier joueur (dealer), 2 = second joueur
    #[ORM\Column(type: 'smallint')]
    private int $position;

    /**
     * Main du joueur : tableau de cartes au format [['value'=>5,'suit'=>'H'], ...]
     * Stocké en JSON, jamais exposé à l'adversaire via l'API.
     */
    #[ORM\Column(type: 'json')]
    private array $hand = [];

    // Nombre de plis remportés dans cette partie
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $tricksWon = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $lastAckedStateVersion = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    // Getters / Setters
    public function getId(): ?string { return $this->id; }
    public function getGame(): Game { return $this->game; }
    public function setGame(Game $game): static { $this->game = $game; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $p): static { $this->position = $p; return $this; }
    public function getHand(): array { return $this->hand; }
    public function setHand(array $hand): static { $this->hand = $hand; return $this; }

    public function removeCardFromHand(int $value, string $suit): static
    {
        $this->hand = array_values(array_filter(
            $this->hand,
            fn($c) => !($c['value'] === $value && $c['suit'] === $suit)
        ));
        return $this;
    }

    public function hasCardOfSuit(string $suit): bool
    {
        return (bool) array_filter($this->hand, fn($c) => $c['suit'] === $suit);
    }

    public function getTricksWon(): int { return $this->tricksWon; }
    public function incrementTricksWon(): static { $this->tricksWon++; return $this; }
    public function getLastAckedStateVersion(): int { return $this->lastAckedStateVersion; }
    public function setLastAckedStateVersion(int $version): static
    {
        $this->lastAckedStateVersion = max(0, $version);

        return $this;
    }
    public function getLastSeenAt(): ?\DateTimeImmutable { return $this->lastSeenAt; }
    public function touchLastSeenAt(): static { $this->lastSeenAt = new \DateTimeImmutable(); return $this; }
}
