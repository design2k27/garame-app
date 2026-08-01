<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\GamePlayer;
use App\Entity\Round;
use App\Entity\User;
use App\Enum\GameStatus;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GameReconnectControllerTest extends WebTestCase
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

    public function testRejoinReturnsNoneWhenNoActiveGame(): void
    {
        $user = $this->createUser('alice', 'alice@test.com');
        $this->em->flush();

        $this->authenticateClient($user);
        $this->client->request('GET', '/api/games/rejoin');

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();
        $this->assertSame('none', $payload['action']);
        $this->assertNull($payload['game']);
        $this->assertNull($payload['result']);
    }

    public function testRejoinReturnsFullStateForActiveGame(): void
    {
        [$user1, , $game] = $this->createPlayingGame();
        $this->em->flush();

        $this->authenticateClient($user1);
        $this->client->request('GET', '/api/games/rejoin');

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();
        $this->assertSame('rejoined', $payload['action']);
        $this->assertSame($game->getId(), $payload['game']['id']);
        $this->assertSame('playing', $payload['game']['status']);
        $this->assertGreaterThanOrEqual(1, $payload['game']['stateVersion']);
        $this->assertArrayHasKey('currentRoundData', $payload['game']);
    }

    public function testSyncReturnsInSyncWhenVersionsMatch(): void
    {
        [$user1, , $game] = $this->createPlayingGame();
        $this->em->flush();

        $this->authenticateClient($user1);
        $this->client->request('GET', sprintf('/api/games/%s/sync?sinceVersion=%d', $game->getId(), $game->getStateVersion()));

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();
        $this->assertSame('synced', $payload['action']);
        $this->assertTrue($payload['inSync']);
        $this->assertSame($game->getStateVersion(), $payload['serverVersion']);
        $this->assertSame($game->getStateVersion(), $payload['game']['stateVersion']);
    }

    public function testSyncReturnsOutOfSyncWhenVersionsDiffer(): void
    {
        [$user1, , $game] = $this->createPlayingGame();
        $game->bumpStateVersion();
        $this->em->flush();

        $this->authenticateClient($user1);
        $this->client->request('GET', sprintf('/api/games/%s/sync?sinceVersion=1', $game->getId()));

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();
        $this->assertSame('synced', $payload['action']);
        $this->assertFalse($payload['inSync']);
        $this->assertSame($game->getStateVersion(), $payload['serverVersion']);
    }

    public function testSyncRejectsInvalidSinceVersion(): void
    {
        [$user1, , $game] = $this->createPlayingGame();
        $this->em->flush();

        $this->authenticateClient($user1);
        $this->client->request('GET', sprintf('/api/games/%s/sync?sinceVersion=0', $game->getId()));

        $this->assertResponseStatusCodeSame(400);

        $payload = $this->decodeResponse();
        $this->assertSame('invalid_state_version', $payload['error']['code']);
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

    private function createPlayingGame(): array
    {
        $user1 = $this->createUser('alice', 'alice@test.com');
        $user2 = $this->createUser('bob', 'bob@test.com');

        $game = new Game();
        $game->setStatus(GameStatus::PLAYING);
        $game->setCurrentLeader($user1);
        $this->em->persist($game);

        $player1 = new GamePlayer();
        $player1->setUser($user1);
        $player1->setPosition(1);
        $player1->setHand([
            ['value' => 6, 'suit' => 'H'],
            ['value' => 7, 'suit' => 'D'],
        ]);
        $game->addPlayer($player1);
        $this->em->persist($player1);

        $player2 = new GamePlayer();
        $player2->setUser($user2);
        $player2->setPosition(2);
        $player2->setHand([
            ['value' => 5, 'suit' => 'H'],
            ['value' => 8, 'suit' => 'C'],
        ]);
        $game->addPlayer($player2);
        $this->em->persist($player2);

        $round = new Round();
        $round->setNumber(1);
        $round->setLeader($user1);
        $game->addRound($round);
        $this->em->persist($round);

        return [$user1, $user2, $game];
    }

    private function authenticateClient(User $user): void
    {
        $tokenManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        $token = $tokenManager->create($user);

        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
    }

    private function decodeResponse(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
