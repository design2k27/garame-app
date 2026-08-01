<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\GamePlayer;
use App\Entity\User;
use App\Exception\ApiException;
use App\Repository\GamePlayerRepository;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class GameApplicationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GameRepository $gameRepository,
        private readonly GamePlayerRepository $gamePlayerRepository,
        private readonly UserRepository $userRepository,
        private readonly GameEngine $gameEngine,
        private readonly MercurePublisher $mercurePublisher,
        private readonly int $matchmakingTimeoutSeconds = 30,
        private readonly int $matchmakingMaxRetries = 3,
    ) {}

    /**
     * @return Game[]
     */
    public function openGames(): array
    {
        return $this->gameRepository->findOpenGames();
    }

    /**
     * @return Game[]
     */
    public function activeGamesForUser(User $user): array
    {
        return $this->gameRepository->findActiveGamesForUser($user);
    }

    /**
     * @return array{games: Game[], pagination: array{page: int, perPage: int, total: int, totalPages: int}}
     */
    public function finishedGamesForUser(User $user, int $page, int $perPage): array
    {
        $total = $this->gameRepository->countFinishedGamesForUser($user);
        $games = $this->gameRepository->findFinishedGamesForUser($user, $page, $perPage);

        return [
            'games' => $games,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return array{action: string, game: Game, result: array, statusCode: int}
     */
    public function create(User $user): array
    {
        return $this->em->wrapInTransaction(function () use ($user) {
            $this->lockUserForUpdate($user);
            $this->assertUserHasNoActiveGame($user);

            $game = new Game();
            $this->em->persist($game);

            $gamePlayer = new GamePlayer();
            $gamePlayer->setUser($user);
            $gamePlayer->setPosition(1);
            $game->addPlayer($gamePlayer);
            $this->em->persist($gamePlayer);

            $this->em->flush();

            return [
                'action' => 'created',
                'game' => $game,
                'result' => ['status' => $game->getStatus()->value],
                'statusCode' => Response::HTTP_CREATED,
            ];
        });
    }

    /**
     * @return array{action: string, game: Game, result: array, statusCode: int}
     */
    public function matchmaking(User $user): array
    {
        return $this->runMatchmakingWithRetry(function () use ($user) {
            return $this->em->wrapInTransaction(function () use ($user) {
                $this->lockUserForUpdate($user);
                $deadline = $this->matchmakingDeadline();

                $this->cleanupExpiredWaitingMatchmakingQueues($deadline);

                $existingQueueGame = $this->gameRepository->findUserWaitingMatchmakingGameForUpdate($user);
                if ($existingQueueGame !== null && $existingQueueGame->getStartedAt() >= $deadline) {
                    return [
                        'action' => 'queued',
                        'game' => $existingQueueGame,
                        'result' => ['status' => $existingQueueGame->getStatus()->value],
                        'statusCode' => Response::HTTP_OK,
                    ];
                }

                $this->assertUserHasNoActiveGame($user);

                $game = $this->gameRepository->findMatchmakingCandidateForUpdate($user, $deadline);
                if (!$game) {
                    $game = new Game();
                    $game->setIsMatchmakingQueue(true);
                    $this->em->persist($game);

                    $gamePlayer = new GamePlayer();
                    $gamePlayer->setUser($user);
                    $gamePlayer->setPosition(1);
                    $game->addPlayer($gamePlayer);
                    $this->em->persist($gamePlayer);
                    $this->em->flush();

                    return [
                        'action' => 'created',
                        'game' => $game,
                        'result' => ['status' => $game->getStatus()->value],
                        'statusCode' => Response::HTTP_CREATED,
                    ];
                }

                return $this->joinLockedGame($game, $user);
            });
        });
    }

    /**
     * @return array{action: string, gameId: ?string, statusCode: int}
     */
    public function cancelMatchmaking(User $user): array
    {
        return $this->em->wrapInTransaction(function () use ($user) {
            $this->lockUserForUpdate($user);
            $game = $this->gameRepository->findUserWaitingMatchmakingGameForUpdate($user);

            if (!$game) {
                return [
                    'action' => 'cancelled',
                    'gameId' => null,
                    'statusCode' => Response::HTTP_OK,
                ];
            }

            foreach ($this->gamePlayerRepository->findByGame($game) as $player) {
                $this->em->remove($player);
            }

            $gameId = $game->getId();
            $this->em->remove($game);
            $this->em->flush();

            return [
                'action' => 'cancelled',
                'gameId' => $gameId,
                'statusCode' => Response::HTTP_OK,
            ];
        });
    }

    /**
     * @return array{action: string, game: Game, result: array, statusCode: int}
     */
    public function join(string $gameId, User $user): array
    {
        return $this->em->wrapInTransaction(function () use ($gameId, $user) {
            $this->lockUserForUpdate($user);
            $game = $this->gameRepository->findForUpdate($gameId);
            if (!$game) {
                throw new ApiException(
                    'game_not_found',
                    'Partie introuvable.',
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->joinLockedGame($game, $user);
        });
    }

    /**
     * @return array{action: string, gameId: string, statusCode: int}
     */
    public function cancel(string $gameId, User $user): array
    {
        return $this->em->wrapInTransaction(function () use ($gameId, $user) {
            $this->lockUserForUpdate($user);
            $game = $this->gameRepository->findForUpdate($gameId);
            if (!$game) {
                throw new ApiException(
                    'game_not_found',
                    'Partie introuvable.',
                    Response::HTTP_NOT_FOUND
                );
            }

            if (!$game->isWaiting()) {
                throw new ApiException(
                    'game_cannot_be_cancelled',
                    'Seules les parties en attente peuvent être annulées.',
                    Response::HTTP_CONFLICT
                );
            }

            $me = $this->gamePlayerRepository->findByGameAndUser($game, $user);
            if (!$me) {
                throw new ApiException(
                    'game_access_denied',
                    'Accès refusé.',
                    Response::HTTP_FORBIDDEN
                );
            }

            foreach ($this->gamePlayerRepository->findByGame($game) as $player) {
                $this->em->remove($player);
            }

            $gameId = $game->getId();
            $this->em->remove($game);
            $this->em->flush();

            return [
                'action' => 'cancelled',
                'gameId' => $gameId,
                'statusCode' => Response::HTTP_OK,
            ];
        });
    }

    /**
     * @return array{game: Game, me: GamePlayer, opponent: ?GamePlayer}
     */
    public function show(string $gameId, User $user): array
    {
        $game = $this->gameRepository->findWithFullRelations($gameId);
        if (!$game) {
            throw new ApiException(
                'game_not_found',
                'Partie introuvable.',
                Response::HTTP_NOT_FOUND
            );
        }

        $me = $this->gamePlayerRepository->findByGameAndUser($game, $user);
        if (!$me) {
            throw new ApiException(
                'game_access_denied',
                'Accès refusé.',
                Response::HTTP_FORBIDDEN
            );
        }

        return [
            'game' => $game,
            'me' => $me,
            'opponent' => $this->gamePlayerRepository->findOpponent($game, $user),
        ];
    }

    /**
     * @return array{game: ?Game, me: ?GamePlayer, opponent: ?GamePlayer}
     */
    public function rejoin(User $user): array
    {
        $games = $this->gameRepository->findActiveGamesForUser($user);
        if ($games === []) {
            return [
                'game' => null,
                'me' => null,
                'opponent' => null,
            ];
        }

        $game = $this->gameRepository->findWithFullRelations($games[0]->getId());
        if (!$game) {
            return [
                'game' => null,
                'me' => null,
                'opponent' => null,
            ];
        }

        $me = $this->gamePlayerRepository->findByGameAndUser($game, $user);
        if (!$me) {
            return [
                'game' => null,
                'me' => null,
                'opponent' => null,
            ];
        }

        return [
            'game' => $game,
            'me' => $me,
            'opponent' => $this->gamePlayerRepository->findOpponent($game, $user),
        ];
    }

    /**
     * @return array{game: Game, me: GamePlayer, opponent: ?GamePlayer, inSync: bool, serverVersion: int}
     */
    public function sync(string $gameId, User $user, ?int $sinceVersion): array
    {
        $shown = $this->show($gameId, $user);
        $serverVersion = $shown['game']->getStateVersion();

        return [
            'game' => $shown['game'],
            'me' => $shown['me'],
            'opponent' => $shown['opponent'],
            'inSync' => $sinceVersion !== null && $sinceVersion === $serverVersion,
            'serverVersion' => $serverVersion,
        ];
    }

    /**
     * @return array{action: string, changed: bool, serverVersion: int, game: ?Game, me: ?GamePlayer, opponent: ?GamePlayer, pollAfterMs: int}
     */
    public function poll(string $gameId, User $user, int $sinceVersion): array
    {
        $sync = $this->sync($gameId, $user, $sinceVersion);
        if ($sync['inSync']) {
            return [
                'action' => 'polled',
                'changed' => false,
                'serverVersion' => $sync['serverVersion'],
                'game' => null,
                'me' => null,
                'opponent' => null,
                'pollAfterMs' => 1500,
            ];
        }

        return [
            'action' => 'polled',
            'changed' => true,
            'serverVersion' => $sync['serverVersion'],
            'game' => $sync['game'],
            'me' => $sync['me'],
            'opponent' => $sync['opponent'],
            'pollAfterMs' => 1500,
        ];
    }

    /**
     * @return array{action: string, gameId: string, ackedStateVersion: int, serverVersion: int, inSync: bool, statusCode: int}
     */
    public function acknowledgeState(string $gameId, User $user, int $stateVersion): array
    {
        return $this->em->wrapInTransaction(function () use ($gameId, $user, $stateVersion) {
            $game = $this->gameRepository->findForUpdate($gameId);
            if (!$game) {
                throw new ApiException(
                    'game_not_found',
                    'Partie introuvable.',
                    Response::HTTP_NOT_FOUND
                );
            }

            $me = $this->gamePlayerRepository->findByGameAndUser($game, $user);
            if (!$me) {
                throw new ApiException(
                    'game_access_denied',
                    'Accès refusé.',
                    Response::HTTP_FORBIDDEN
                );
            }

            $serverVersion = $game->getStateVersion();
            if ($stateVersion > $serverVersion) {
                throw new ApiException(
                    'invalid_state_version',
                    'stateVersion ne peut pas dépasser la version serveur.',
                    Response::HTTP_BAD_REQUEST,
                    ['serverVersion' => $serverVersion]
                );
            }

            $me->setLastAckedStateVersion(max($me->getLastAckedStateVersion(), $stateVersion));
            $me->touchLastSeenAt();
            $this->em->flush();

            return [
                'action' => 'acked',
                'gameId' => $game->getId(),
                'ackedStateVersion' => $me->getLastAckedStateVersion(),
                'serverVersion' => $serverVersion,
                'inSync' => $me->getLastAckedStateVersion() === $serverVersion,
                'statusCode' => Response::HTTP_OK,
            ];
        });
    }

    /**
     * @return array{action: string, status: string, originalGameId: string, game: ?Game, me: ?GamePlayer, opponent: ?GamePlayer, result: ?array, statusCode: int}
     */
    public function rematch(string $gameId, User $user): array
    {
        return $this->em->wrapInTransaction(function () use ($gameId, $user) {
            $originalGame = $this->gameRepository->findForUpdate($gameId);
            if (!$originalGame) {
                throw new ApiException(
                    'game_not_found',
                    'Partie introuvable.',
                    Response::HTTP_NOT_FOUND
                );
            }

            if (!$originalGame->isFinished()) {
                throw new ApiException(
                    'game_unavailable',
                    'La revanche est disponible uniquement apres une partie terminee.',
                    Response::HTTP_CONFLICT
                );
            }

            $me = $this->gamePlayerRepository->findByGameAndUser($originalGame, $user);
            if (!$me) {
                throw new ApiException(
                    'game_access_denied',
                    'Acces refuse.',
                    Response::HTTP_FORBIDDEN
                );
            }

            $opponent = $this->gamePlayerRepository->findOpponent($originalGame, $user);
            if (!$opponent) {
                throw new ApiException(
                    'game_unavailable',
                    'Aucun adversaire disponible pour une revanche.',
                    Response::HTTP_CONFLICT
                );
            }

            if ($originalGame->getRematchGameId() !== null) {
                $newGame = $this->gameRepository->findWithFullRelations($originalGame->getRematchGameId());
                if ($newGame) {
                    $newMe = $this->gamePlayerRepository->findByGameAndUser($newGame, $user);

                    return [
                        'action' => 'rematch_started',
                        'status' => 'started',
                        'originalGameId' => $originalGame->getId(),
                        'game' => $newGame,
                        'me' => $newMe,
                        'opponent' => $this->gamePlayerRepository->findOpponent($newGame, $user),
                        'result' => null,
                        'statusCode' => Response::HTTP_OK,
                    ];
                }
            }

            $originalGame->addRematchRequest($user);

            if (!$originalGame->hasRematchRequestFrom($opponent->getUser())) {
                $this->em->flush();

                return [
                    'action' => 'rematch_waiting',
                    'status' => 'waiting',
                    'originalGameId' => $originalGame->getId(),
                    'game' => null,
                    'me' => null,
                    'opponent' => null,
                    'result' => null,
                    'statusCode' => Response::HTTP_OK,
                ];
            }

            $this->assertUserHasNoActiveGame($user);
            $this->assertUserHasNoActiveGame($opponent->getUser());

            $newGame = new Game();
            $this->em->persist($newGame);

            $firstPlayer = new GamePlayer();
            $firstPlayer->setUser($me->getPosition() === 1 ? $me->getUser() : $opponent->getUser());
            $firstPlayer->setPosition(1);
            $newGame->addPlayer($firstPlayer);
            $this->em->persist($firstPlayer);

            $secondPlayer = new GamePlayer();
            $secondPlayer->setUser($me->getPosition() === 1 ? $opponent->getUser() : $me->getUser());
            $secondPlayer->setPosition(2);
            $newGame->addPlayer($secondPlayer);
            $this->em->persist($secondPlayer);

            $this->em->flush();

            $result = $this->gameEngine->startGame($newGame);
            $newGame->bumpStateVersion();
            $originalGame->setRematchGameId($newGame->getId());
            $this->em->flush();
            $this->mercurePublisher->publishGameStarted($newGame, $result);

            $newMe = $this->gamePlayerRepository->findByGameAndUser($newGame, $user);

            return [
                'action' => 'rematch_started',
                'status' => 'started',
                'originalGameId' => $originalGame->getId(),
                'game' => $newGame,
                'me' => $newMe,
                'opponent' => $this->gamePlayerRepository->findOpponent($newGame, $user),
                'result' => $result,
                'statusCode' => Response::HTTP_CREATED,
            ];
        });
    }

    private function assertUserHasNoActiveGame(User $user): void
    {
        if ($this->gameRepository->isUserInActiveGame($user)) {
            throw new ApiException(
                'active_game_exists',
                'Vous êtes déjà dans une partie en cours.',
                Response::HTTP_CONFLICT
            );
        }
    }

    private function lockUserForUpdate(User $user): void
    {
        $lockedUser = $this->userRepository->findForUpdate((string) $user->getId());
        if ($lockedUser === null) {
            throw new ApiException(
                'unauthenticated',
                'Non authentifié.',
                Response::HTTP_UNAUTHORIZED
            );
        }
    }

    private function matchmakingDeadline(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(sprintf('-%d seconds', max(1, $this->matchmakingTimeoutSeconds)));
    }

    private function cleanupExpiredWaitingMatchmakingQueues(\DateTimeImmutable $deadline): void
    {
        $expiredGames = $this->gameRepository->findExpiredWaitingMatchmakingGames($deadline);
        foreach ($expiredGames as $game) {
            foreach ($this->gamePlayerRepository->findByGame($game) as $player) {
                $this->em->remove($player);
            }
            $this->em->remove($game);
        }

        if ($expiredGames !== []) {
            $this->em->flush();
        }
    }

    /**
     * @param callable(): array $callback
     *
     * @return array
     */
    private function runMatchmakingWithRetry(callable $callback): array
    {
        $attempts = max(1, $this->matchmakingMaxRetries);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $callback();
            } catch (UniqueConstraintViolationException|RetryableException $e) {
                if ($attempt === $attempts) {
                    throw $e;
                }
            }
        }

        return $callback();
    }

    /**
     * @return array{action: string, game: Game, result: array, statusCode: int}
     */
    private function joinLockedGame(Game $game, User $user): array
    {
        if (!$game->isWaiting()) {
            throw new ApiException(
                'game_unavailable',
                'Cette partie n\'est plus disponible.',
                Response::HTTP_CONFLICT
            );
        }

        if ($game->isFull()) {
            throw new ApiException(
                'game_full',
                'Cette partie est complète.',
                Response::HTTP_CONFLICT
            );
        }

        if ($this->gamePlayerRepository->findByGameAndUser($game, $user)) {
            throw new ApiException(
                'already_in_game',
                'Vous êtes déjà dans cette partie.',
                Response::HTTP_CONFLICT
            );
        }

        $this->assertUserHasNoActiveGame($user);

        $gamePlayer = new GamePlayer();
        $gamePlayer->setUser($user);
        $gamePlayer->setPosition(2);
        $game->addPlayer($gamePlayer);
        $this->em->persist($gamePlayer);
        $this->em->flush();

        $result = $this->gameEngine->startGame($game);
        $game->bumpStateVersion();
        $this->em->flush();
        $this->mercurePublisher->publishGameStarted($game, $result);

        return [
            'action' => 'joined',
            'game' => $game,
            'result' => $result,
            'statusCode' => Response::HTTP_OK,
        ];
    }
}
