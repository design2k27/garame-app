<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\GamePlayer;
use App\Entity\GameResult;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Enum\WinType;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GameHistoryControllerTest extends WebTestCase
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

    public function testHistoryReturnsPaginatedFinishedGames(): void
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $bob = $this->createUser('bob', 'bob@test.com');
        $carol = $this->createUser('carol', 'carol@test.com');

        $this->createFinishedGame($alice, $bob, $alice, WinType::MATCH_SIMPLE);
        $this->createFinishedGame($carol, $alice, $carol, WinType::KORAT);
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request('GET', '/api/games/my/history?page=1&perPage=1');

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();

        $this->assertSame('listed', $payload['action']);
        $this->assertCount(1, $payload['items']);
        $this->assertSame(1, $payload['pagination']['page']);
        $this->assertSame(1, $payload['pagination']['perPage']);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertSame(2, $payload['pagination']['totalPages']);
        $this->assertArrayHasKey('opponent', $payload['items'][0]);
        $this->assertArrayHasKey('result', $payload['items'][0]);
    }

    public function testHistoryMarksVictoryStatusCorrectly(): void
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $bob = $this->createUser('bob', 'bob@test.com');

        $this->createFinishedGame($alice, $bob, $alice, WinType::MATCH_SIMPLE);
        $this->createFinishedGame($alice, $bob, $bob, WinType::KORAT);
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request('GET', '/api/games/my/history?perPage=10');

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();

        $didWinValues = array_map(fn(array $item) => $item['didWin'], $payload['items']);

        $this->assertContains(true, $didWinValues);
        $this->assertContains(false, $didWinValues);
    }

    public function testHistoryRejectsInvalidPerPage(): void
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request('GET', '/api/games/my/history?perPage=0');

        $this->assertResponseStatusCodeSame(400);

        $payload = $this->decodeResponse();

        $this->assertSame('invalid_per_page', $payload['error']['code']);
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

    private function createFinishedGame(User $player1, User $player2, User $winner, WinType $winType): void
    {
        $game = new Game();
        $game->setStatus(GameStatus::FINISHED);
        $game->setWinner($winner);
        $game->setWinType($winType);
        $game->setEndedAt(new \DateTimeImmutable());
        $this->em->persist($game);

        $gp1 = new GamePlayer();
        $gp1->setUser($player1);
        $gp1->setPosition(1);
        $game->addPlayer($gp1);
        $this->em->persist($gp1);

        $gp2 = new GamePlayer();
        $gp2->setUser($player2);
        $gp2->setPosition(2);
        $game->addPlayer($gp2);
        $this->em->persist($gp2);

        $loser = $winner->getId() === $player1->getId() ? $player2 : $player1;

        $result = new GameResult();
        $result->setGame($game);
        $result->setWinner($winner);
        $result->setLoser($loser);
        $result->setWinType($winType);
        $result->setStakeMultiplier($winType === WinType::KORAT ? 2 : 1);
        $game->setResult($result);
        $this->em->persist($result);
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
