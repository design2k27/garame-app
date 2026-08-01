<?php
namespace App\Repository;

use App\Entity\Game;
use App\Entity\Round;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Round::class);
    }

    public function findCurrentRound(Game $game): ?Round
    {
        /** @var Round[] $rounds */
        $rounds = $this->createQueryBuilder('r')
            ->where('r.game = :game')
            ->setParameter('game', $game)
            ->orderBy('r.number', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($rounds as $round) {
            if ($round->getWinner() === null) {
                return $round;
            }
        }

        return null;
    }

    public function findCompletedRounds(Game $game): array
    {
        $rounds = $this->createQueryBuilder('r')
            ->where('r.game = :game')
            ->setParameter('game', $game)
            ->orderBy('r.number', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($rounds, fn($r) => $r->getWinner() !== null));
    }

    public function findLastRound(Game $game): ?Round
    {
        return $this->createQueryBuilder('r')
            ->where('r.game = :game')
            ->setParameter('game', $game)
            ->orderBy('r.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countCompletedRounds(Game $game): int
    {
        $rounds = $this->createQueryBuilder('r')
            ->where('r.game = :game')
            ->setParameter('game', $game)
            ->getQuery()
            ->getResult();

        return count(array_filter($rounds, fn($r) => $r->getWinner() !== null));
    }
}
