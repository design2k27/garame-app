<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HomeStatusService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
        private readonly string $mercureUrl = '',
        private readonly string $messengerTransportDsn = '',
    ) {}

    public function getStatuses(): array
    {
        $database = $this->checkDatabase();

        return [
            $database,
            $this->checkMercure(),
            $this->checkMessenger($database),
        ];
    }

    public function countHealthy(array $statuses): int
    {
        return count(array_filter($statuses, static fn(array $status) => $status['state'] === 'up'));
    }

    private function checkDatabase(): array
    {
        $startedAt = microtime(true);

        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();

            return [
                'name' => 'Base de donnees',
                'state' => 'up',
                'label' => 'Connectee',
                'detail' => sprintf(
                    'Connexion Doctrine OK en %d ms.',
                    $this->getDurationMs($startedAt)
                ),
                'meta' => $this->safeDatabaseName(),
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Base de donnees',
                'state' => 'down',
                'label' => 'Indisponible',
                'detail' => $e->getMessage(),
                'meta' => $this->safeDatabaseName(),
            ];
        }
    }

    private function checkMercure(): array
    {
        if ($this->mercureUrl === '') {
            return [
                'name' => 'Hub Mercure',
                'state' => 'warn',
                'label' => 'Non configure',
                'detail' => 'La variable MERCURE_URL est absente.',
                'meta' => null,
            ];
        }

        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->request('HEAD', $this->mercureUrl, [
                'timeout' => 2.0,
                'max_redirects' => 0,
            ]);
            $statusCode = $response->getStatusCode();

            $state = $statusCode < 500 ? 'up' : 'down';

            return [
                'name' => 'Hub Mercure',
                'state' => $state,
                'label' => $state === 'up' ? 'Joignable' : 'Erreur',
                'detail' => sprintf(
                    'Reponse HTTP %d en %d ms.',
                    $statusCode,
                    $this->getDurationMs($startedAt)
                ),
                'meta' => $this->mercureUrl,
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Hub Mercure',
                'state' => 'down',
                'label' => 'Indisponible',
                'detail' => $e->getMessage(),
                'meta' => $this->mercureUrl,
            ];
        }
    }

    private function checkMessenger(array $databaseStatus): array
    {
        if ($this->messengerTransportDsn === '') {
            return [
                'name' => 'Transport async',
                'state' => 'warn',
                'label' => 'Non configure',
                'detail' => 'La variable MESSENGER_TRANSPORT_DSN est absente.',
                'meta' => null,
            ];
        }

        $scheme = strtolower((string) parse_url($this->messengerTransportDsn, PHP_URL_SCHEME));

        if ($scheme === 'doctrine') {
            return [
                'name' => 'Transport async',
                'state' => $databaseStatus['state'],
                'label' => $databaseStatus['state'] === 'up' ? 'Operationnel' : 'Indisponible',
                'detail' => 'Le transport Messenger utilise Doctrine et partage la connexion SQL.',
                'meta' => $this->messengerTransportDsn,
            ];
        }

        $host = (string) parse_url($this->messengerTransportDsn, PHP_URL_HOST);
        $port = (int) (parse_url($this->messengerTransportDsn, PHP_URL_PORT) ?: $this->defaultPortForScheme($scheme));

        if ($host === '' || $port === 0) {
            return [
                'name' => 'Transport async',
                'state' => 'warn',
                'label' => 'Non verifie',
                'detail' => 'DSN detecte, mais le host ou le port ne peuvent pas etre derives automatiquement.',
                'meta' => $this->messengerTransportDsn,
            ];
        }

        $startedAt = microtime(true);
        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 2.0);

        if (is_resource($socket)) {
            fclose($socket);

            return [
                'name' => 'Transport async',
                'state' => 'up',
                'label' => 'Joignable',
                'detail' => sprintf(
                    'Connexion reseau %s:%d OK en %d ms.',
                    $host,
                    $port,
                    $this->getDurationMs($startedAt)
                ),
                'meta' => $this->messengerTransportDsn,
            ];
        }

        return [
            'name' => 'Transport async',
            'state' => 'down',
            'label' => 'Indisponible',
            'detail' => sprintf('Connexion %s:%d impossible (%s).', $host, $port, $errorMessage ?: $errorCode),
            'meta' => $this->messengerTransportDsn,
        ];
    }

    private function getDurationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function safeDatabaseName(): ?string
    {
        $params = $this->connection->getParams();

        return is_string($params['dbname'] ?? null) ? $params['dbname'] : null;
    }

    private function defaultPortForScheme(string $scheme): int
    {
        return match ($scheme) {
            'redis', 'rediss' => 6379,
            'amqp', 'amqps' => 5672,
            'http' => 80,
            'https' => 443,
            default => 0,
        };
    }
}
