<?php
// src/Repository/GameResultRepository.php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\GameResult;
use App\Entity\User;
use App\Enum\WinType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameResult>
 */
class GameResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameResult::class);
    }

    /**
     * Résultat d'une partie spécifique.
     */
    public function findByGame(Game $game): ?GameResult
    {
        return $this->findOneBy(['game' => $game]);
    }

    /**
     * Statistiques agrégées d'un joueur :
     * total victoires, défaites, Korat gagnés, Korat subis.
     *
     * @return array{wins: int, losses: int, korat_won: int, korat_lost: int}
     */
    public function getStatsForUser(User $user): array
    {
        $wins = (int) $this->createQueryBuilder('gr')
            ->select('COUNT(gr.id)')
            ->where('gr.winner = :user')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();

        $losses = (int) $this->createQueryBuilder('gr')
            ->select('COUNT(gr.id)')
            ->where('gr.loser = :user')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();

        $koratWon = (int) $this->createQueryBuilder('gr')
            ->select('COUNT(gr.id)')
            ->where('gr.winner = :user')
            ->andWhere('gr.winType = :korat')
            ->setParameter('user', $user)
            ->setParameter('korat', WinType::KORAT)
            ->getQuery()->getSingleScalarResult();

        $koratLost = (int) $this->createQueryBuilder('gr')
            ->select('COUNT(gr.id)')
            ->where('gr.loser = :user')
            ->andWhere('gr.winType = :korat')
            ->setParameter('user', $user)
            ->setParameter('korat', WinType::KORAT)
            ->getQuery()->getSingleScalarResult();

        return [
            'wins'       => $wins,
            'losses'     => $losses,
            'korat_won'  => $koratWon,
            'korat_lost' => $koratLost,
        ];
    }

    /**
     * Derniers résultats d'un joueur (feed activité).
     *
     * @return GameResult[]
     */
    public function findRecentForUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('gr')
            ->leftJoin('gr.winner', 'w')->addSelect('w')
            ->leftJoin('gr.loser', 'l')->addSelect('l')
            ->where('gr.winner = :user OR gr.loser = :user')
            ->setParameter('user', $user)
            ->orderBy('gr.createdAt', 'DESC')
            ->addOrderBy('gr.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, array{winType: string, wins: string|int, losses: string|int}>
     */
    public function aggregateByWinTypeForUser(User $user): array
    {
        return $this->createQueryBuilder('gr')
            ->select('gr.winType AS winType')
            ->addSelect('SUM(CASE WHEN gr.winner = :user THEN 1 ELSE 0 END) AS wins')
            ->addSelect('SUM(CASE WHEN gr.loser = :user THEN 1 ELSE 0 END) AS losses')
            ->where('gr.winner = :user OR gr.loser = :user')
            ->setParameter('user', $user)
            ->groupBy('gr.winType')
            ->getQuery()
            ->getArrayResult();
    }
}
