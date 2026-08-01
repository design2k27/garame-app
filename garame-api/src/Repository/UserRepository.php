<?php
// src/Repository/UserRepository.php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $user->setPasswordHash($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function findForUpdate(string $id): ?User
    {
        $query = $this->createQueryBuilder('u')
            ->where('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery();

        if ($this->supportsPessimisticLocks()) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        return $query->getOneOrNullResult();
    }

    /**
     * Classement des joueurs par credits décroissants, puis victoires.
     *
     * @return User[]
     */
    public function findLeaderboard(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.credits', 'DESC')
            ->addOrderBy('u.gamesWon', 'DESC')
            ->addOrderBy('u.gamesPlayed', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie l'unicité de l'email ET du username en une seule requête.
     * Retourne un tableau ['email' => bool, 'username' => bool].
     */
    public function checkUniqueness(string $email, string $username): array
    {
        $results = $this->createQueryBuilder('u')
            ->select('u.email', 'u.username')
            ->where('u.email = :email OR u.username = :username')
            ->setParameter('email', $email)
            ->setParameter('username', $username)
            ->getQuery()
            ->getResult();

        $taken = ['email' => false, 'username' => false];
        foreach ($results as $row) {
            if ($row['email'] === $email) $taken['email'] = true;
            if ($row['username'] === $username) $taken['username'] = true;
        }

        return $taken;
    }

    private function supportsPessimisticLocks(): bool
    {
        return !$this->getEntityManager()->getConnection()->getDatabasePlatform() instanceof SQLitePlatform;
    }
}
