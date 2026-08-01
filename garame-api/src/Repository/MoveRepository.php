<?php
// src/Repository/MoveRepository.php

namespace App\Repository;

use App\Entity\Move;
use App\Entity\Round;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Move>
 */
class MoveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Move::class);
    }

    /**
     * Le coup déjà joué par un joueur dans un pli donné.
     * Permet de vérifier qu'un joueur ne joue pas deux fois.
     */
    public function findByRoundAndUser(Round $round, User $user): ?Move
    {
        return $this->findOneBy(['round' => $round, 'user' => $user]);
    }

    /**
     * Les deux coups d'un pli, ordonnés (meneur en premier).
     *
     * @return Move[]
     */
    public function findByRoundOrdered(Round $round): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')->addSelect('u')
            ->where('m.round = :round')
            ->setParameter('round', $round)
            ->orderBy('m.playOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un joueur a déjà joué dans le pli courant.
     */
    public function hasUserPlayedInRound(Round $round, User $user): bool
    {
        $count = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.round = :round')
            ->andWhere('m.user = :user')
            ->setParameter('round', $round)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
