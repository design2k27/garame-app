<?php

namespace App\Tests\Unit;

use App\Service\HomeStatusService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class HomeStatusServiceTest extends TestCase
{
    public function testGetStatusesReturnsUpStatesWhenDatabaseAndMercureAreReachable(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())
            ->method('fetchOne')
            ->willReturn(1);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($result);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'garame_test']);

        $mercureResponse = $this->createMock(ResponseInterface::class);
        $mercureResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(308);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'HEAD',
                'http://mercure.local/.well-known/mercure',
                $this->callback(static fn(array $options) => ($options['timeout'] ?? null) === 2.0)
            )
            ->willReturn($mercureResponse);

        $service = new HomeStatusService(
            $connection,
            $httpClient,
            'http://mercure.local/.well-known/mercure',
            'doctrine://default?auto_setup=0'
        );

        $statuses = $service->getStatuses();

        $this->assertCount(3, $statuses);
        $this->assertSame('Base de donnees', $statuses[0]['name']);
        $this->assertSame('up', $statuses[0]['state']);
        $this->assertSame('garame_test', $statuses[0]['meta']);
        $this->assertSame('Hub Mercure', $statuses[1]['name']);
        $this->assertSame('up', $statuses[1]['state']);
        $this->assertSame('Transport async', $statuses[2]['name']);
        $this->assertSame('up', $statuses[2]['state']);
        $this->assertSame(3, $service->countHealthy($statuses));
    }

    public function testGetStatusesMarksDatabaseAndTransportDownWhenSqlConnectionFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willThrowException(new \RuntimeException('SQL unavailable'));
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'garame_test']);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $service = new HomeStatusService(
            $connection,
            $httpClient,
            '',
            'doctrine://default?auto_setup=0'
        );

        $statuses = $service->getStatuses();

        $this->assertSame('down', $statuses[0]['state']);
        $this->assertSame('warn', $statuses[1]['state']);
        $this->assertSame('down', $statuses[2]['state']);
        $this->assertSame(0, $service->countHealthy($statuses));
    }

    public function testGetStatusesMarksMercureDownWhenHttpRequestFails(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())
            ->method('fetchOne')
            ->willReturn(1);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($result);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'garame_test']);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Mercure offline'));

        $service = new HomeStatusService(
            $connection,
            $httpClient,
            'http://mercure.local/.well-known/mercure',
            'doctrine://default?auto_setup=0'
        );

        $statuses = $service->getStatuses();

        $this->assertSame('up', $statuses[0]['state']);
        $this->assertSame('down', $statuses[1]['state']);
        $this->assertStringContainsString('Mercure offline', $statuses[1]['detail']);
        $this->assertSame('up', $statuses[2]['state']);
        $this->assertSame(2, $service->countHealthy($statuses));
    }
}
