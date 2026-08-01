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

class GameSuccessResponseTest extends WebTestCase
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

    public function testCreateReturnsUnifiedSuccessPayload(): void
    {
        $user = $this->createUser('alice', 'alice@test.com');
        $this->em->flush();

        $this->authenticateClient($user);
        $this->client->request('POST', '/api/games');

        $this->assertResponseStatusCodeSame(201);

        $payload = $this->decodeResponse();

        $this->assertSame('created', $payload['action']);
        $this->assertSame('waiting', $payload['result']['status']);
        $this->assertNull($payload['result']['roundWinner']);
        $this->assertNull($payload['result']['nextLeader']);
        $this->assertNull($payload['result']['nextRound']);
        $this->assertSame('waiting', $payload['game']['status']);
        $this->assertGreaterThanOrEqual(1, $payload['game']['stateVersion']);
        $this->assertCount(1, $payload['game']['players']);
    }

    public function testShowReturnsUnifiedSuccessPayload(): void
    {
        $user = $this->createUser('alice', 'alice@test.com');
        $game = $this->createWaitingGame($user);
        $this->em->flush();

        $this->authenticateClient($user);
        $this->client->request('GET', sprintf('/api/games/%s', $game->getId()));

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();

        $this->assertSame('fetched', $payload['action']);
        $this->assertSame('waiting', $payload['result']['status']);
        $this->assertNull($payload['result']['roundWinner']);
        $this->assertSame($game->getId(), $payload['game']['id']);
        $this->assertArrayHasKey('myHand', $payload['game']);
        $this->assertArrayHasKey('stateVersion', $payload['game']);
        $this->assertArrayHasKey('currentRoundData', $payload['game']);
    }

    public function testJoinReturnsUnifiedSuccessPayload(): void
    {
        $host = $this->createUser('alice', 'alice@test.com');
        $seeker = $this->createUser('bob', 'bob@test.com');
        $game = $this->createWaitingGame($host);
        $this->em->flush();

        $this->authenticateClient($seeker);
        $this->client->request('POST', sprintf('/api/games/%s/join', $game->getId()));

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();

        $this->assertSame('joined', $payload['action']);
        $this->assertContains($payload['result']['status'], ['playing', 'finished']);
        $this->assertSame($game->getId(), $payload['game']['id']);
        $this->assertCount(2, $payload['game']['players']);
    }

    public function testPlayReturnsUnifiedSuccessPayload(): void
    {
        [$user1, $user2, $game] = $this->createPlayingGame();
        $this->em->flush();

        $this->authenticateClient($user1);
        $this->client->request(
            'POST',
            sprintf('/api/games/%s/play', $game->getId()),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['value' => 6, 'suit' => 'H'], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();

        $this->assertSame('played', $payload['action']);
        $this->assertSame('waiting', $payload['result']['status']);
        $this->assertNull($payload['result']['roundWinner']);
        $this->assertNull($payload['result']['nextLeader']);
        $this->assertNull($payload['result']['nextRound']);
        $this->assertSame($game->getId(), $payload['game']['id']);
        $this->assertSame('alice', $payload['game']['currentLeader']);
        $this->assertCount(1, $payload['game']['myHand']);
        $this->assertCount(1, $payload['game']['currentRoundData']['moves']);
    }

    public function testPlayReturnsRoundOutcomeWhenTrickCompletes(): void
    {
        [$user1, $user2, $game] = $this->createPlayingGame();
        $this->em->flush();

        $this->authenticateClient($user1);
        $this->client->request(
            'POST',
            sprintf('/api/games/%s/play', $game->getId()),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['value' => 6, 'suit' => 'H'], JSON_THROW_ON_ERROR)
        );

        $this->authenticateClient($user2);
        $this->client->request(
            'POST',
            sprintf('/api/games/%s/play', $game->getId()),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['value' => 5, 'suit' => 'H'], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();

        $this->assertSame('played', $payload['action']);
        $this->assertSame('round_complete', $payload['result']['status']);
        $this->assertSame('alice', $payload['result']['roundWinner']);
        $this->assertSame('alice', $payload['result']['nextLeader']);
        $this->assertSame(2, $payload['result']['nextRound']);
        $this->assertSame(2, $payload['game']['currentRound']);
        $this->assertSame('alice', $payload['game']['currentLeader']);
        $this->assertCount(1, $payload['game']['rounds']);
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

    private function createWaitingGame(User $host): Game
    {
        $game = new Game();
        $this->em->persist($game);

        $gamePlayer = new GamePlayer();
        $gamePlayer->setUser($host);
        $gamePlayer->setPosition(1);
        $game->addPlayer($gamePlayer);
        $this->em->persist($gamePlayer);

        return $game;
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
