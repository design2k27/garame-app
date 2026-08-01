<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthSuccessResponseTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->hasher = $container->get(UserPasswordHasherInterface::class);

        $this->em->getConnection()->executeStatement('DELETE FROM move');
        $this->em->getConnection()->executeStatement('DELETE FROM round');
        $this->em->getConnection()->executeStatement('DELETE FROM game_result');
        $this->em->getConnection()->executeStatement('DELETE FROM game_player');
        $this->em->getConnection()->executeStatement('DELETE FROM game');
        $this->em->getConnection()->executeStatement('DELETE FROM "user"');
    }

    public function testRegisterReturnsTokenAndUser(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'username' => 'alice',
                'email' => 'alice@test.com',
                'password' => 'secret123',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(201);

        $payload = $this->decodeResponse();

        $this->assertArrayHasKey('token', $payload);
        $this->assertSame('alice', $payload['user']['username']);
        $this->assertSame('alice@test.com', $payload['user']['email']);
        $this->assertSame(0, $payload['user']['credits']);
    }

    public function testLoginReturnsTokenAndUser(): void
    {
        $user = $this->createUser('alice', 'alice@test.com', 'secret123');
        $this->em->flush();

        $this->client->request(
            'POST',
            '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'alice@test.com',
                'password' => 'secret123',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();

        $this->assertArrayHasKey('token', $payload);
        $this->assertSame($user->getEmail(), $payload['user']['email']);
        $this->assertSame(0, $payload['user']['credits']);
    }

    public function testMeReturnsAuthenticatedUser(): void
    {
        $user = $this->createUser('alice', 'alice@test.com', 'secret123');
        $this->em->flush();

        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        $this->client->request('GET', '/api/auth/me');

        $this->assertResponseIsSuccessful();

        $payload = $this->decodeResponse();

        $this->assertSame('alice', $payload['user']['username']);
        $this->assertSame('alice@test.com', $payload['user']['email']);
        $this->assertSame(0, $payload['user']['credits']);
    }

    private function createUser(string $username, string $email, string $password): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);

        return $user;
    }

    private function decodeResponse(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
