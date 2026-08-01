<?php
// src/Entity/Move.php

namespace App\Entity;

use App\Repository\MoveRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MoveRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Move
{
    // Couleurs valides du jeu Garame
    public const SUITS = ['H', 'D', 'C', 'S']; // Heart, Diamond, Club, Spade
    // Valeurs valides (3 à 8, le 8S est retiré dans CardDeckService)
    public const VALUES = [3, 4, 5, 6, 7, 8];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'moves')]
    #[ORM\JoinColumn(nullable: false)]
    private Round $round;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    // 1 = meneur (carte ouvrant le pli), 2 = répondant
    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 1, max: 2)]
    private int $playOrder;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Choice(choices: [3, 4, 5, 6, 7, 8])]
    private int $cardValue;

    #[ORM\Column(type: 'string', length: 1)]
    #[Assert\Choice(choices: ['H', 'D', 'C', 'S'])]
    private string $cardSuit;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $playedAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->playedAt = new \DateTimeImmutable();
    }

    // Helper : représentation lisible de la carte
    public function toCardArray(): array
    {
        return ['value' => $this->cardValue, 'suit' => $this->cardSuit];
    }

    // Getters / Setters
    public function getId(): ?string { return $this->id; }
    public function getRound(): Round { return $this->round; }
    public function setRound(Round $round): static { $this->round = $round; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }
    public function getPlayOrder(): int { return $this->playOrder; }
    public function setPlayOrder(int $o): static { $this->playOrder = $o; return $this; }
    public function getCardValue(): int { return $this->cardValue; }
    public function setCardValue(int $v): static { $this->cardValue = $v; return $this; }
    public function getCardSuit(): string { return $this->cardSuit; }
    public function setCardSuit(string $s): static { $this->cardSuit = $s; return $this; }
    public function getPlayedAt(): \DateTimeImmutable { return $this->playedAt; }
}
