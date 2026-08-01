<?php
// src/Entity/Game.php

namespace App\Entity;

use App\Enum\GameStatus;
use App\Enum\WinType;
use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?string $id = null;

    #[ORM\Column(type: 'string', enumType: GameStatus::class)]
    private GameStatus $status = GameStatus::WAITING;

    // Numéro du pli en cours (1 à 5)
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $currentRound = 1;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $stateVersion = 1;

    // Joueur qui a la main actuellement
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $currentLeader = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $winner = null;

    #[ORM\Column(type: 'string', enumType: WinType::class, nullable: true)]
    private ?WinType $winType = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isMatchmakingQueue = false;

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $rematchRequests = [];

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?string $rematchGameId = null;

    #[ORM\OneToMany(mappedBy: 'game', targetEntity: GamePlayer::class, cascade: ['persist'])]
    private Collection $players;

    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Round::class, cascade: ['persist'])]
    private Collection $rounds;

    #[ORM\OneToOne(mappedBy: 'game', targetEntity: GameResult::class, cascade: ['persist'])]
    private ?GameResult $result = null;

    public function __construct()
    {
        $this->players = new ArrayCollection();
        $this->rounds  = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->startedAt = new \DateTimeImmutable();
    }

    public function isFull(): bool { return $this->players->count() >= 2; }
    public function isWaiting(): bool { return $this->status === GameStatus::WAITING; }
    public function isPlaying(): bool { return $this->status === GameStatus::PLAYING; }
    public function isFinished(): bool { return $this->status === GameStatus::FINISHED; }

    // Getters / Setters
    public function getId(): ?string { return $this->id; }
    public function getStatus(): GameStatus { return $this->status; }
    public function setStatus(GameStatus $status): static { $this->status = $status; return $this; }
    public function getCurrentRound(): int { return $this->currentRound; }
    public function incrementCurrentRound(): static { $this->currentRound++; return $this; }
    public function getStateVersion(): int { return $this->stateVersion; }
    public function bumpStateVersion(): static { $this->stateVersion++; return $this; }
    public function getCurrentLeader(): ?User { return $this->currentLeader; }
    public function setCurrentLeader(?User $user): static { $this->currentLeader = $user; return $this; }
    public function getWinner(): ?User { return $this->winner; }
    public function setWinner(?User $user): static { $this->winner = $user; return $this; }
    public function getWinType(): ?WinType { return $this->winType; }
    public function setWinType(?WinType $type): static { $this->winType = $type; return $this; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getEndedAt(): ?\DateTimeImmutable { return $this->endedAt; }
    public function setEndedAt(\DateTimeImmutable $dt): static { $this->endedAt = $dt; return $this; }
    public function isMatchmakingQueue(): bool { return $this->isMatchmakingQueue; }
    public function setIsMatchmakingQueue(bool $isMatchmakingQueue): static
    {
        $this->isMatchmakingQueue = $isMatchmakingQueue;

        return $this;
    }
    public function getRematchRequests(): array { return $this->rematchRequests; }
    public function hasRematchRequestFrom(User $user): bool
    {
        return in_array($user->getId(), $this->rematchRequests, true);
    }
    public function addRematchRequest(User $user): static
    {
        if (!$this->hasRematchRequestFrom($user)) {
            $this->rematchRequests[] = $user->getId();
        }

        return $this;
    }
    public function getRematchGameId(): ?string { return $this->rematchGameId; }
    public function setRematchGameId(?string $rematchGameId): static
    {
        $this->rematchGameId = $rematchGameId;

        return $this;
    }
    public function getPlayers(): Collection { return $this->players; }
    public function addPlayer(GamePlayer $player): static
    {
        if (!$this->players->contains($player)) {
            $this->players->add($player);
        }

        $player->setGame($this);

        return $this;
    }
    public function getRounds(): Collection { return $this->rounds; }
    public function addRound(Round $round): static
    {
        if (!$this->rounds->contains($round)) {
            $this->rounds->add($round);
        }

        $round->setGame($this);

        return $this;
    }
    public function getResult(): ?GameResult { return $this->result; }
    public function setResult(GameResult $result): static { $this->result = $result; return $this; }
}
