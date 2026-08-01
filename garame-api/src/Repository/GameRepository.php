<?php
// src/Repository/GameRepository.php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * Parties en attente d'un second joueur (lobby).
     *
     * @return Game[]
     */
    public function findOpenGames(): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.players', 'p')
            ->addSelect('p')
            ->where('g.status = :status')
            ->andWhere('g.isMatchmakingQueue = false')
            ->setParameter('status', GameStatus::WAITING)
            ->orderBy('g.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne la meilleure partie en attente pour le matchmaking.
     * Exclut toute partie déjà liée à l'utilisateur.
     */
    public function findMatchmakingCandidate(User $user, ?\DateTimeImmutable $notExpiredSince = null): ?Game
    {
        return $this->createMatchmakingCandidateQuery($user, $notExpiredSince)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findMatchmakingCandidateForUpdate(User $user, ?\DateTimeImmutable $notExpiredSince = null): ?Game
    {
        $query = $this->createMatchmakingCandidateQuery($user, $notExpiredSince)->getQuery();

        if ($this->supportsPessimisticLocks()) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        return $query->getOneOrNullResult();
    }

    public function findUserWaitingMatchmakingGameForUpdate(User $user): ?Game
    {
        $query = $this->createQueryBuilder('g')
            ->innerJoin('g.players', 'gp')
            ->where('g.status = :status')
            ->andWhere('g.isMatchmakingQueue = true')
            ->andWhere('gp.user = :user')
            ->setParameter('status', GameStatus::WAITING)
            ->setParameter('user', $user)
            ->orderBy('g.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery();

        if ($this->supportsPessimisticLocks()) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        return $query->getOneOrNullResult();
    }

    /**
     * @return Game[]
     */
    public function findExpiredWaitingMatchmakingGames(\DateTimeImmutable $deadline): array
    {
        return $this->createQueryBuilder('g')
            ->innerJoin('g.players', 'gp')
            ->where('g.status = :status')
            ->andWhere('g.isMatchmakingQueue = true')
            ->andWhere('g.startedAt < :deadline')
            ->setParameter('status', GameStatus::WAITING)
            ->setParameter('deadline', $deadline)
            ->groupBy('g.id')
            ->having('COUNT(gp.id) = 1')
            ->getQuery()
            ->getResult();
    }

    public function findForUpdate(string $id): ?Game
    {
        $query = $this->createQueryBuilder('g')
            ->where('g.id = :id')
            ->setParameter('id', $id)
            ->getQuery();

        if ($this->supportsPessimisticLocks()) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        return $query->getOneOrNullResult();
    }

    private function createMatchmakingCandidateQuery(User $user, ?\DateTimeImmutable $notExpiredSince = null)
    {
        $qb = $this->createQueryBuilder('g')
            ->innerJoin('g.players', 'gp')
            ->addSelect('gp')
            ->where('g.status = :status')
            ->andWhere('g.isMatchmakingQueue = true')
            ->andWhere('g.id NOT IN (
                SELECT g2.id
                FROM App\Entity\Game g2
                JOIN g2.players gp2
                WHERE gp2.user = :user
            )')
            ->setParameter('status', GameStatus::WAITING)
            ->setParameter('user', $user)
            ->orderBy('g.startedAt', 'ASC')
            ->setMaxResults(1);

        if ($notExpiredSince !== null) {
            $qb
                ->andWhere('g.startedAt >= :notExpiredSince')
                ->setParameter('notExpiredSince', $notExpiredSince);
        }

        return $qb;
    }

    private function supportsPessimisticLocks(): bool
    {
        return !$this->getEntityManager()->getConnection()->getDatabasePlatform() instanceof SQLitePlatform;
    }

    /**
     * Charge une partie avec toutes ses relations en une seule requête
     * pour éviter les N+1 dans GameEngine.
     */
    public function findWithFullRelations(string $id): ?Game
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.players', 'gp')->addSelect('gp')
            ->leftJoin('gp.user', 'u')->addSelect('u')
            ->leftJoin('g.rounds', 'r')->addSelect('r')
            ->leftJoin('r.moves', 'm')->addSelect('m')
            ->leftJoin('m.user', 'mu')->addSelect('mu')
            ->leftJoin('g.currentLeader', 'cl')->addSelect('cl')
            ->where('g.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Toutes les parties actives (WAITING ou PLAYING) d'un joueur.
     *
     * @return Game[]
     */
    public function findActiveGamesForUser(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.players', 'gp')
            ->where('gp.user = :user')
            ->andWhere('g.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [GameStatus::WAITING, GameStatus::PLAYING])
            ->orderBy('g.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique des parties terminées d'un joueur, paginé.
     *
     * @return Game[]
     */
    public function findFinishedGamesForUser(User $user, int $page = 1, int $perPage = 10): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.players', 'gp')
            ->leftJoin('g.result', 'gr')->addSelect('gr')
            ->where('gp.user = :user')
            ->andWhere('g.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', GameStatus::FINISHED)
            ->orderBy('g.endedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countFinishedGamesForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(DISTINCT g.id)')
            ->leftJoin('g.players', 'gp')
            ->where('gp.user = :user')
            ->andWhere('g.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', GameStatus::FINISHED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie qu'un utilisateur n'est pas déjà dans une partie en cours.
     */
    public function isUserInActiveGame(User $user): bool
    {
        $count = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->leftJoin('g.players', 'gp')
            ->where('gp.user = :user')
            ->andWhere('g.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [GameStatus::WAITING, GameStatus::PLAYING])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
