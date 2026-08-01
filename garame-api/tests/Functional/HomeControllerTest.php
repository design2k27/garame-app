<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function testHomePageRendersServiceStatusBlocks(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();

        $this->assertIsString($content);
        $this->assertStringContainsString('L\'API tourne correctement.', $content);
        $this->assertStringContainsString('Base de donnees', $content);
        $this->assertStringContainsString('Hub Mercure', $content);
        $this->assertStringContainsString('Transport async', $content);
        $this->assertStringContainsString('dependances OK', $content);
    }

    public function testHealthEndpointReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseFormatSame('json');
        $this->assertJsonStringEqualsJsonString(
            json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR),
            (string) $client->getResponse()->getContent()
        );
    }

    public function testRulesPageIsPublicAndContainsGameRules(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reglement');

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();

        $this->assertIsString($content);
        $this->assertStringContainsString('Reglement complet du Garame', $content);
        $this->assertStringContainsString('8S', $content);
        $this->assertStringContainsString('three_seven', $content);
        $this->assertStringContainsString('korat', $content);
    }

    public function testApiDocumentationPageIsPublicAndContainsHttpDocs(): void
    {
        $client = static::createClient();
        $client->request('GET', '/documentation/api');

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();

        $this->assertIsString($content);
        $this->assertStringContainsString('Documentation complete de l\'API', $content);
        $this->assertStringContainsString('POST /api/auth/register', $content);
        $this->assertStringContainsString('GET /api/games/rejoin', $content);
        $this->assertStringContainsString('Guide Client Temps', $content);
    }
}
