<?php
// src/Entity/User.php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?string $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    private string $username;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column]
    private string $passwordHash;

    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $gamesPlayed = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $gamesWon = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $credits = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: GamePlayer::class)]
    private Collection $gamePlayers;

    public function __construct()
    {
        $this->gamePlayers = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // --- UserInterface ---

    public function getUserIdentifier(): string { return $this->email; }
    public function getRoles(): array { return $this->roles; }
    public function eraseCredentials(): void {}
    public function getPassword(): string { return $this->passwordHash; }

    // --- Getters / Setters ---

    public function getId(): ?string { return $this->id; }
    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function setPasswordHash(string $hash): static { $this->passwordHash = $hash; return $this; }
    public function getGamesPlayed(): int { return $this->gamesPlayed; }
    public function incrementGamesPlayed(): static { $this->gamesPlayed++; return $this; }
    public function getGamesWon(): int { return $this->gamesWon; }
    public function incrementGamesWon(): static { $this->gamesWon++; return $this; }
    public function getCredits(): int { return $this->credits; }
    public function setCredits(int $credits): static { $this->credits = $credits; return $this; }
    public function adjustCredits(int $delta): static { $this->credits += $delta; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getGamePlayers(): Collection { return $this->gamePlayers; }
}
