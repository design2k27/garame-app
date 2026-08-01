<?php
// src/Repository/GamePlayerRepository.php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\GamePlayer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GamePlayer>
 */
class GamePlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GamePlayer::class);
    }

    /**
     * Récupère le GamePlayer d'un User dans une partie donnée.
     */
    public function findByGameAndUser(Game $game, User $user): ?GamePlayer
    {
        return $this->findOneBy(['game' => $game, 'user' => $user]);
    }

    /**
     * Retourne les deux joueurs d'une partie, triés par position.
     *
     * @return GamePlayer[]
     */
    public function findByGame(Game $game): array
    {
        return $this->createQueryBuilder('gp')
            ->leftJoin('gp.user', 'u')->addSelect('u')
            ->where('gp.game = :game')
            ->setParameter('game', $game)
            ->orderBy('gp.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère l'adversaire d'un joueur dans une partie.
     */
    public function findOpponent(Game $game, User $user): ?GamePlayer
    {
        return $this->createQueryBuilder('gp')
            ->leftJoin('gp.user', 'u')->addSelect('u')
            ->where('gp.game = :game')
            ->andWhere('gp.user != :user')
            ->setParameter('game', $game)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie qu'un joueur possède bien une carte avant de la jouer.
     * Utile comme garde-fou dans MoveController avant d'appeler GameEngine.
     */
    public function playerHasCard(Game $game, User $user, int $value, string $suit): bool
    {
        $gp = $this->findByGameAndUser($game, $user);
        if (!$gp) return false;

        foreach ($gp->getHand() as $card) {
            if ($card['value'] === $value && $card['suit'] === $suit) {
                return true;
            }
        }

        return false;
    }
}
