<?php
// src/Entity/GameResult.php

namespace App\Entity;

use App\Enum\WinType;
use App\Repository\GameResultRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameResultRepository::class)]
#[ORM\HasLifecycleCallbacks]
class GameResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?string $id = null;

    #[ORM\OneToOne(targetEntity: Game::class, inversedBy: 'result')]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $winner;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $loser;

    #[ORM\Column(type: 'string', enumType: WinType::class)]
    private WinType $winType;

    /**
     * Multiplicateur de mise : 1 = normal, 2 = Korat
     */
    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private int $stakeMultiplier = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters / Setters
    public function getId(): ?string { return $this->id; }
    public function getGame(): Game { return $this->game; }
    public function setGame(Game $game): static { $this->game = $game; return $this; }
    public function getWinner(): User { return $this->winner; }
    public function setWinner(User $user): static { $this->winner = $user; return $this; }
    public function getLoser(): User { return $this->loser; }
    public function setLoser(User $user): static { $this->loser = $user; return $this; }
    public function getWinType(): WinType { return $this->winType; }
    public function setWinType(WinType $type): static { $this->winType = $type; return $this; }
    public function getStakeMultiplier(): int { return $this->stakeMultiplier; }
    public function setStakeMultiplier(int $m): static { $this->stakeMultiplier = $m; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
