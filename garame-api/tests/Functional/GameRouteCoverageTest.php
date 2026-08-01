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

class GameRouteCoverageTest extends WebTestCase
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

    public function testOpenGamesReturnsListedPayload(): void
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $bob = $this->createUser('bob', 'bob@test.com');
        $this->createWaitingGame($alice);
        $this->createWaitingGame($bob);
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request('GET', '/api/games/open');

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();

        $this->assertSame('listed', $payload['action']);
        $this->assertCount(2, $payload['games']);
    }

    public function testMyActiveReturnsListedPayload(): void
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $this->createWaitingGame($alice);
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request('GET', '/api/games/my/active');

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();

        $this->assertSame('listed', $payload['action']);
        $this->assertCount(1, $payload['games']);
        $this->assertSame('waiting', $payload['games'][0]['status']);
        $this->assertSame(0, $payload['games'][0]['players'][0]['credits']);
    }

    public function testRankingReturnsUsersOrderedByCredits(): void
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $bob = $this->createUser('bob', 'bob@test.com');
        $carol = $this->createUser('carol', 'carol@test.com');

        $alice->setCredits(30);
        $bob->setCredits(-10);
        $carol->setCredits(15);
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request('GET', '/api/ranking');

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();

        $this->assertSame('listed', $payload['action']);
        $this->assertCount(3, $payload['items']);
        $this->assertSame('alice', $payload['items'][0]['username']);
        $this->assertSame(30, $payload['items'][0]['credits']);
        $this->assertSame(1, $payload['items'][0]['rank']);
        $this->assertSame('carol', $payload['items'][1]['username']);
        $this->assertSame('bob', $payload['items'][2]['username']);
    }

    public function testShowReturnsForbiddenForNonParticipant(): void
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $bob = $this->createUser('bob', 'bob@test.com');
        $game = $this->createWaitingGame($alice);
        $this->em->flush();

        $this->authenticateClient($bob);
        $this->client->request('GET', sprintf('/api/games/%s', $game->getId()));

        $this->assertResponseStatusCodeSame(403);

        $payload = $this->decodeResponse();

        $this->assertSame('game_access_denied', $payload['error']['code']);
    }

    public function testJoinReturnsNotFoundForUnknownGame(): void
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request('POST', '/api/games/00000000-0000-0000-0000-000000000000/join');

        $this->assertResponseStatusCodeSame(404);

        $payload = $this->decodeResponse();

        $this->assertSame('game_not_found', $payload['error']['code']);
    }

    public function testCancelWaitingGameRemovesIt(): void
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $game = $this->createWaitingGame($alice);
        $gameId = $game->getId();
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request('POST', sprintf('/api/games/%s/cancel', $gameId));

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();

        $this->assertSame('cancelled', $payload['action']);
        $this->assertSame($gameId, $payload['gameId']);
        $this->assertNull($this->em->getRepository(Game::class)->find($gameId));
    }

    public function testCancelReturnsForbiddenForNonParticipant(): void
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $bob = $this->createUser('bob', 'bob@test.com');
        $game = $this->createWaitingGame($alice);
        $this->em->flush();

        $this->authenticateClient($bob);
        $this->client->request('POST', sprintf('/api/games/%s/cancel', $game->getId()));

        $this->assertResponseStatusCodeSame(403);

        $payload = $this->decodeResponse();

        $this->assertSame('game_access_denied', $payload['error']['code']);
    }

    public function testCancelReturnsConflictForPlayingGame(): void
    {
        [$alice, , $game] = $this->createPlayingGame();
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request('POST', sprintf('/api/games/%s/cancel', $game->getId()));

        $this->assertResponseStatusCodeSame(409);

        $payload = $this->decodeResponse();

        $this->assertSame('game_cannot_be_cancelled', $payload['error']['code']);
    }

    public function testPlayReturnsRuleViolationWhenCardIsNotOwned(): void
    {
        [$alice, , $game] = $this->createPlayingGame();
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request(
            'POST',
            sprintf('/api/games/%s/play', $game->getId()),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['value' => 8, 'suit' => 'D'], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(422);

        $payload = $this->decodeResponse();

        $this->assertSame('rule_violation', $payload['error']['code']);
        $this->assertSame('Vous ne possédez pas cette carte.', $payload['error']['message']);
    }

    public function testPlayRejectsCardOutsideGarameDeck(): void
    {
        [$alice, , $game] = $this->createPlayingGame();
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request(
            'POST',
            sprintf('/api/games/%s/play', $game->getId()),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['value' => 8, 'suit' => 'S'], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(422);

        $payload = $this->decodeResponse();

        $this->assertSame('rule_violation', $payload['error']['code']);
        $this->assertSame('Cette carte n\'appartient pas au paquet Garame.', $payload['error']['message']);
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

        $player = new GamePlayer();
        $player->setUser($host);
        $player->setPosition(1);
        $game->addPlayer($player);
        $this->em->persist($player);

        return $game;
    }

    private function createPlayingGame(): array
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $bob = $this->createUser('bob', 'bob@test.com');

        $game = new Game();
        $game->setStatus(GameStatus::PLAYING);
        $game->setCurrentLeader($alice);
        $this->em->persist($game);

        $player1 = new GamePlayer();
        $player1->setUser($alice);
        $player1->setPosition(1);
        $player1->setHand([
            ['value' => 6, 'suit' => 'H'],
            ['value' => 7, 'suit' => 'D'],
        ]);
        $game->addPlayer($player1);
        $this->em->persist($player1);

        $player2 = new GamePlayer();
        $player2->setUser($bob);
        $player2->setPosition(2);
        $player2->setHand([
            ['value' => 5, 'suit' => 'H'],
            ['value' => 8, 'suit' => 'C'],
        ]);
        $game->addPlayer($player2);
        $this->em->persist($player2);

        $round = new Round();
        $round->setNumber(1);
        $round->setLeader($alice);
        $game->addRound($round);
        $this->em->persist($round);

        return [$alice, $bob, $game];
    }

    private function authenticateClient(User $user): void
    {
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
    }

    private function decodeResponse(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
