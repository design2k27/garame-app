<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\GameResult;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Enum\WinType;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfileStatsControllerTest extends WebTestCase
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

    public function testStatsReturnsEmptyProfileWhenNoFinishedGameExists(): void
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request('GET', '/api/profile/stats');

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();
        $this->assertSame('fetched', $payload['action']);
        $this->assertSame(0, $payload['stats']['summary']['totalGames']);
        $this->assertSame(0, $payload['stats']['summary']['wins']);
        $this->assertSame(0, $payload['stats']['summary']['losses']);
        $this->assertEquals(0.0, $payload['stats']['summary']['winRate']);
        $this->assertSame('none', $payload['stats']['streak']['type']);
        $this->assertSame(0, $payload['stats']['streak']['count']);
        $this->assertSame([], $payload['stats']['recent']);
        $this->assertArrayHasKey('korat', $payload['stats']['byWinType']);
    }

    public function testStatsReturnsDetailedAggregatesAndRecentMatches(): void
    {
        $alice = $this->createUser('alice', 'alice@test.com');
        $bob = $this->createUser('bob', 'bob@test.com');
        $carol = $this->createUser('carol', 'carol@test.com');
        $this->em->flush();

        $this->createFinishedResult($bob, $alice, WinType::MATCH_SIMPLE);
        usleep(2000);
        $this->createFinishedResult($alice, $bob, WinType::KORAT, 2);
        usleep(2000);
        $this->createFinishedResult($alice, $carol, WinType::THREE_SEVEN);

        $alice->setCredits(20);
        $alice->incrementGamesPlayed()->incrementGamesPlayed()->incrementGamesPlayed();
        $alice->incrementGamesWon()->incrementGamesWon();
        $this->em->flush();

        $this->authenticateClient($alice);
        $this->client->request('GET', '/api/profile/stats');

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();
        $summary = $payload['stats']['summary'];
        $this->assertSame(3, $summary['totalGames']);
        $this->assertSame(2, $summary['wins']);
        $this->assertSame(1, $summary['losses']);
        $this->assertSame(66.67, $summary['winRate']);

        $this->assertSame(1, $payload['stats']['byWinType']['three_seven']['wins']);
        $this->assertSame(1, $payload['stats']['byWinType']['korat']['wins']);
        $this->assertSame(1, $payload['stats']['byWinType']['match_simple']['losses']);

        $this->assertSame('win', $payload['stats']['streak']['type']);
        $this->assertSame(2, $payload['stats']['streak']['count']);

        $this->assertCount(3, $payload['stats']['recent']);
        $this->assertTrue($payload['stats']['recent'][0]['didWin']);
        $this->assertSame('three_seven', $payload['stats']['recent'][0]['winType']);
        $this->assertArrayHasKey('creditsDelta', $payload['stats']['recent'][0]);
        $this->assertArrayHasKey('opponent', $payload['stats']['recent'][0]);
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

    private function createFinishedResult(User $winner, User $loser, WinType $winType, int $stakeMultiplier = 1): void
    {
        $game = new Game();
        $game->setStatus(GameStatus::FINISHED);
        $game->setWinner($winner);
        $game->setWinType($winType);
        $game->setEndedAt(new \DateTimeImmutable());
        $this->em->persist($game);

        $result = new GameResult();
        $result->setGame($game);
        $result->setWinner($winner);
        $result->setLoser($loser);
        $result->setWinType($winType);
        $result->setStakeMultiplier($stakeMultiplier);

        $this->em->persist($result);
        $this->em->flush();
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
