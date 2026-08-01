# Garame API

Backend API du jeu de cartes Garame, construit avec Symfony 7.4, PostgreSQL, JWT et Mercure.

## Fonctionnalités

- Authentification JWT (`register`, `login`, `me`)
- Gestion des parties Garame 1v1 (`create`, `matchmaking`, `join`, `cancel`, `play`)
- Historique paginé des parties
- Classement global des joueurs (`/api/ranking`)
- Notifications temps réel via Mercure

## Stack technique

- PHP `8.4` (image Docker) / minimum projet `>= 8.2`
- Symfony `7.4`
- Doctrine ORM + Doctrine Migrations
- PostgreSQL
- LexikJWTAuthenticationBundle
- Mercure
- PHPUnit

## Démarrage rapide (Docker)

### Prérequis

- Docker
- Docker Compose v2

### Lancer en mode "prod local"

```bash
docker compose -f compose.prod.yaml up --build
```

Services exposés par défaut:

- API: `http://localhost:8080`
- PostgreSQL: `localhost:5432`
- Mercure: `http://localhost:3000/.well-known/mercure`
- Redis: `localhost:6379`

Le conteneur applicatif:

- attend PostgreSQL
- crée la base si nécessaire
- exécute les migrations (`RUN_MIGRATIONS=1`)
- génère les clés JWT si absentes

## Démarrage local (sans Docker)

### Prérequis

- PHP 8.2+
- Composer
- PostgreSQL

### Setup

```bash
composer install
cp .env .env.local
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
symfony server:start
```

## Documentation API

- Documentation principale: [`docs/API.md`](docs/API.md)
- Alias de compatibilité casse: [`docs/Api.md`](docs/Api.md)
- Guide intégration front temps réel: [`docs/RealtimeClient.md`](docs/RealtimeClient.md)
- Index documentation technique: [`docs/README.md`](docs/README.md)
- Architecture interne: [`docs/Architecture.md`](docs/Architecture.md)
- Modèle de données: [`docs/Entities.md`](docs/Entities.md)
- Services métier et applicatifs: [`docs/Services.md`](docs/Services.md)
- Cartographie des routes: [`docs/Routes.md`](docs/Routes.md)
- OpenAPI (API Platform): `GET /api/docs` (actuellement protégé par JWT)

## Endpoints principaux

- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/me`
- `GET /api/games/open`
- `POST /api/games`
- `POST /api/games/matchmaking`
- `POST /api/games/matchmaking/cancel`
- `GET /api/games/my/active`
- `GET /api/games/my/history?page=1&perPage=10`
- `GET /api/games/rejoin`
- `GET /api/games/{id}`
- `GET /api/games/{id}/sync?sinceVersion=3`
- `GET /api/games/{id}/poll?sinceVersion=3`
- `POST /api/games/{id}/ack`
- `GET /api/profile/stats`
- `POST /api/games/{id}/join`
- `POST /api/games/{id}/cancel`
- `POST /api/games/{id}/play`
- `GET /api/ranking`
- `GET /health`

## Tests

```bash
php bin/phpunit
```

## Arborescence utile

- `src/Controller`: endpoints HTTP
- `src/Service`: logique métier (moteur de jeu, matchmaking, payloads, Mercure)
- `src/Entity`: modèle Doctrine
- `tests/Functional`: tests API / flux métier
- `tests/Unit`: tests unitaires
- `docs/API.md`: contrat API

## Notes

- Toutes les routes `/api` sont protégées sauf `POST /api/auth/register` et `POST /api/auth/login`.
- Le format d'erreur applicatif est standardisé via `error.code` et `error.message`.
- Le matchmaking est piloté par `MATCHMAKING_TIMEOUT_SECONDS` et `MATCHMAKING_MAX_RETRIES`.
