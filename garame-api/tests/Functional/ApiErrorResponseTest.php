<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\GamePlayer;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiErrorResponseTest extends WebTestCase
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

    public function testRegisterReturnsStandardizedErrorForInvalidJson(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{invalid-json'
        );

        $this->assertResponseStatusCodeSame(400);

        $payload = $this->decodeResponse();

        $this->assertSame('invalid_json', $payload['error']['code']);
        $this->assertSame('JSON invalide.', $payload['error']['message']);
        $this->assertArrayNotHasKey('details', $payload['error']);
    }

    public function testRegisterReturnsValidationDetailsInStandardizedFormat(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'username' => 'ab',
                'email' => 'not-an-email',
                'password' => 'secret',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(422);

        $payload = $this->decodeResponse();

        $this->assertSame('validation_failed', $payload['error']['code']);
        $this->assertSame('La validation a échoué.', $payload['error']['message']);
        $this->assertArrayHasKey('details', $payload['error']);
        $this->assertArrayHasKey('username', $payload['error']['details']);
        $this->assertArrayHasKey('email', $payload['error']['details']);
    }

    public function testLoginReturnsStandardizedErrorForInvalidCredentials(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'nobody@test.com',
                'password' => 'wrong-password',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(401);

        $payload = $this->decodeResponse();

        $this->assertSame('invalid_credentials', $payload['error']['code']);
        $this->assertSame('Identifiants invalides.', $payload['error']['message']);
    }

    public function testMatchmakingReturnsStandardizedConflictError(): void
    {
        $user = $this->createUser('alice', 'alice@test.com');
        $this->createWaitingGame($user);
        $this->em->flush();

        $this->authenticateClient($user);
        $this->client->request('POST', '/api/games/matchmaking');

        $this->assertResponseStatusCodeSame(409);

        $payload = $this->decodeResponse();

        $this->assertSame('active_game_exists', $payload['error']['code']);
        $this->assertSame('Vous êtes déjà dans une partie en cours.', $payload['error']['message']);
    }

    public function testPlayReturnsStandardizedBadRequestForMissingPayload(): void
    {
        $user = $this->createUser('alice', 'alice@test.com');
        $game = $this->createWaitingGame($user);
        $this->em->flush();

        $this->authenticateClient($user);
        $this->client->request(
            'POST',
            sprintf('/api/games/%s/play', $game->getId()),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(400);

        $payload = $this->decodeResponse();

        $this->assertSame('missing_card_payload', $payload['error']['code']);
        $this->assertSame('Les champs value et suit sont obligatoires.', $payload['error']['message']);
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
