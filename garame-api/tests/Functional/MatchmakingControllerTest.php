<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\GamePlayer;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MatchmakingControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->getConnection()->executeStatement('DELETE FROM move');
        $this->em->getConnection()->executeStatement('DELETE FROM round');
        $this->em->getConnection()->executeStatement('DELETE FROM game_result');
        $this->em->getConnection()->executeStatement('DELETE FROM game_player');
        $this->em->getConnection()->executeStatement('DELETE FROM game');
        $this->em->getConnection()->executeStatement('DELETE FROM "user"');
    }

    public function testMatchmakingCreatesWaitingGameWhenNoOpenGameExists(): void
    {
        $user = $this->createUser('alice', 'alice@test.com');
        $this->em->flush();

        $this->authenticateClient($user);
        $this->client->request('POST', '/api/games/matchmaking');

        $this->assertResponseStatusCodeSame(201);

        $payload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('created', $payload['action']);
        $this->assertSame('waiting', $payload['result']['status']);
        $this->assertNull($payload['result']['winner']);
        $this->assertNull($payload['result']['winType']);
        $this->assertSame('waiting', $payload['game']['status']);
        $this->assertCount(1, $payload['game']['players']);
        $this->assertSame('alice', $payload['game']['players'][0]['username']);
        $this->assertSame(1, $payload['game']['players'][0]['position']);

        $createdGame = $this->em->getRepository(Game::class)->find($payload['game']['id']);
        $this->assertNotNull($createdGame);
        $this->assertTrue($createdGame->isMatchmakingQueue());
    }

    public function testMatchmakingJoinsExistingWaitingGame(): void
    {
        $host = $this->createUser('alice', 'alice@test.com');
        $seeker = $this->createUser('bob', 'bob@test.com');
        $game = $this->createWaitingGame($host, true);
        $this->em->flush();

        $this->authenticateClient($seeker);
        $this->client->request('POST', '/api/games/matchmaking');

        $this->assertResponseIsSuccessful();

        $payload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('joined', $payload['action']);
        $this->assertContains($payload['result']['status'], ['playing', 'finished']);
        $this->assertSame($game->getId(), $payload['game']['id']);
        $this->assertCount(2, $payload['game']['players']);

        $positionsByUsername = [];
        foreach ($payload['game']['players'] as $player) {
            $positionsByUsername[$player['username']] = $player['position'];
        }

        $this->assertSame(1, $positionsByUsername['alice']);
        $this->assertSame(2, $positionsByUsername['bob']);
        $this->assertContains($payload['game']['status'], ['playing', 'finished']);
    }

    public function testCancelMatchmakingCancelsWaitingQueueEntry(): void
    {
        $user = $this->createUser('alice', 'alice@test.com');
        $game = $this->createWaitingGame($user, true);
        $gameId = $game->getId();
        $this->em->flush();

        $this->authenticateClient($user);
        $this->client->request('POST', '/api/games/matchmaking/cancel');

        $this->assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('cancelled', $payload['action']);
        $this->assertSame($gameId, $payload['gameId']);
        $this->assertNull($this->em->getRepository(Game::class)->find($gameId));
    }

    public function testCancelMatchmakingIsIdempotentWhenNoQueueEntryExists(): void
    {
        $user = $this->createUser('alice', 'alice@test.com');
        $this->em->flush();

        $this->authenticateClient($user);
        $this->client->request('POST', '/api/games/matchmaking/cancel');

        $this->assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('cancelled', $payload['action']);
        $this->assertNull($payload['gameId']);
    }

    public function testMatchmakingRecreatesQueueWhenExistingOneTimedOut(): void
    {
        $user = $this->createUser('alice', 'alice@test.com');
        $expiredGame = $this->createWaitingGame($user, true);
        $expiredGameId = $expiredGame->getId();
        $this->em->flush();

        $this->em->getConnection()->executeStatement(
            'UPDATE game SET started_at = :startedAt WHERE id = :id',
            [
                'startedAt' => (new \DateTimeImmutable('-2 minutes'))->format('Y-m-d H:i:s'),
                'id' => $expiredGameId,
            ]
        );

        $this->authenticateClient($user);
        $this->client->request('POST', '/api/games/matchmaking');

        $this->assertResponseStatusCodeSame(201);
        $payload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('created', $payload['action']);
        $this->assertNotSame($expiredGameId, $payload['game']['id']);
        $this->assertNull($this->em->getRepository(Game::class)->find($expiredGameId));
    }

    public function testMatchmakingReturnsQueuedWhenUserAlreadyWaitingInQueue(): void
    {
        $user = $this->createUser('alice', 'alice@test.com');
        $queuedGame = $this->createWaitingGame($user, true);
        $this->em->flush();

        $this->authenticateClient($user);
        $this->client->request('POST', '/api/games/matchmaking');

        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('queued', $payload['action']);
        $this->assertSame($queuedGame->getId(), $payload['game']['id']);
        $this->assertSame('waiting', $payload['result']['status']);
    }

    public function testMatchmakingRejectsUserAlreadyInActiveGame(): void
    {
        $user = $this->createUser('alice', 'alice@test.com');
        $this->createWaitingGame($user);
        $this->em->flush();

        $this->authenticateClient($user);
        $this->client->request('POST', '/api/games/matchmaking');

        $this->assertResponseStatusCodeSame(409);

        $payload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('active_game_exists', $payload['error']['code']);
        $this->assertSame('Vous êtes déjà dans une partie en cours.', $payload['error']['message']);
    }

    private function createUser(string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hashed');

        $this->em->persist($user);

        return $user;
    }

    private function createWaitingGame(User $host, bool $isMatchmakingQueue = false): Game
    {
        $game = new Game();
        $game->setIsMatchmakingQueue($isMatchmakingQueue);
        $this->em->persist($game);

        $gamePlayer = new GamePlayer();
        $gamePlayer->setUser($host);
        $gamePlayer->setPosition(1);
        $game->addPlayer($gamePlayer);
        $this->em->persist($gamePlayer);

        return $game;
    }

    private function authenticateClient(User $user): void
    {
        $tokenManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        $token = $tokenManager->create($user);

        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
        $this->client->setServerParameter('CONTENT_TYPE', 'application/json');
    }
}
