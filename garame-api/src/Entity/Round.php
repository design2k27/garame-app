<?php
// src/Entity/Round.php

namespace App\Entity;

use App\Enum\WinType;
use App\Repository\RoundRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoundRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Round
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Game::class, inversedBy: 'rounds')]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    // Numéro du pli : 1 à 5
    #[ORM\Column(type: 'smallint')]
    private int $number;

    // Joueur qui ouvre le pli (le meneur)
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $leader;

    // Joueur qui remporte le pli (null tant que non joué)
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $winner = null;

    // MATCH_SIMPLE ou KORAT (uniquement pour le pli 5)
    #[ORM\Column(type: 'string', enumType: WinType::class, nullable: true)]
    private ?WinType $winType = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $playedAt = null;

    #[ORM\OneToMany(mappedBy: 'round', targetEntity: Move::class, cascade: ['persist'])]
    #[ORM\OrderBy(['playOrder' => 'ASC'])]
    private Collection $moves;

    public function __construct()
    {
        $this->moves = new ArrayCollection();
    }

    public function isComplete(): bool { return $this->moves->count() === 2; }

    public function getLeaderMove(): ?Move
    {
        return $this->moves->filter(fn($m) => $m->getPlayOrder() === 1)->first() ?: null;
    }

    public function getResponderMove(): ?Move
    {
        return $this->moves->filter(fn($m) => $m->getPlayOrder() === 2)->first() ?: null;
    }

    // Getters / Setters
    public function getId(): ?string { return $this->id; }
    public function getGame(): Game { return $this->game; }
    public function setGame(Game $game): static { $this->game = $game; return $this; }
    public function getNumber(): int { return $this->number; }
    public function setNumber(int $n): static { $this->number = $n; return $this; }
    public function getLeader(): User { return $this->leader; }
    public function setLeader(User $user): static { $this->leader = $user; return $this; }
    public function getWinner(): ?User { return $this->winner; }
    public function setWinner(?User $user): static { $this->winner = $user; return $this; }
    public function getWinType(): ?WinType { return $this->winType; }
    public function setWinType(?WinType $type): static { $this->winType = $type; return $this; }
    public function getPlayedAt(): ?\DateTimeImmutable { return $this->playedAt; }
    public function setPlayedAt(\DateTimeImmutable $dt): static { $this->playedAt = $dt; return $this; }
    public function getMoves(): Collection { return $this->moves; }
}
