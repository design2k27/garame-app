# Cartographie des routes

Cette note relie chaque route au contrôleur, au service principal et au type de payload produit.

## Routes publiques

### `GET /`

- Contrôleur: `HomeController::index()`
- Service principal: `HomeStatusService`
- Usage: page d'entrée navigateur avec statut des services

### `GET /health`

- Contrôleur: `HomeController::health()`
- Usage: healthcheck simple
- Réponse: `{ "status": "ok" }`

### `GET /reglement`

- Contrôleur: `HomeController::rules()`
- Usage: page publique du règlement Garame

### `GET /documentation/api`

- Contrôleur: `HomeController::apiDocumentation()`
- Usage: page publique de documentation technique/API

## Authentification

Préfixe: `/api/auth`

### `POST /api/auth/register`

- Contrôleur: `AuthController::register()`
- Dépendances principales:
  - `UserPasswordHasherInterface`
  - `JWTTokenManagerInterface`
  - `UserRepository`
  - `ValidatorInterface`
- Réponse: token JWT + payload utilisateur

### `POST /api/auth/login`

- Contrôleur: `AuthController::login()`
- Dépendances principales:
  - `UserRepository`
  - `UserPasswordHasherInterface`
  - `JWTTokenManagerInterface`
- Réponse: token JWT + payload utilisateur

### `GET /api/auth/me`

- Contrôleur: `AuthController::me()`
- Dépendances principales: utilisateur courant Symfony Security
- Réponse: payload utilisateur courant

## Parties

Préfixe: `/api/games`

### `GET /api/games/open`

- Contrôleur: `GameController::openGames()`
- Service principal: `GameApplicationService::openGames()`
- Projection: `GamePayloadBuilder::buildSummary()`
- Usage: liste des parties lobby ouvertes, hors file matchmaking

### `POST /api/games`

- Contrôleur: `GameController::create()`
- Service principal: `GameApplicationService::create()`
- Projection:
  - `buildSummary()`
  - `buildResult()`
- Usage: création d'une partie `waiting`

### `POST /api/games/matchmaking`

- Contrôleur: `GameController::matchmaking()`
- Service principal: `GameApplicationService::matchmaking()`
- Projection:
  - `buildSummary()`
  - `buildResult()`
- Usage:
  - retourne `created` si une file est créée
  - `queued` si le joueur est déjà en attente
  - `joined` si une partie est trouvée

### `POST /api/games/matchmaking/cancel`

- Contrôleur: `GameController::cancelMatchmaking()`
- Service principal: `GameApplicationService::cancelMatchmaking()`
- Réponse: `action`, `gameId`

### `GET /api/games/my/active`

- Contrôleur: `GameController::myActive()`
- Service principal: `GameApplicationService::activeGamesForUser()`
- Projection: `buildSummary()`
- Usage: parties `waiting` ou `playing` du joueur

### `GET /api/games/my/history`

- Contrôleur: `GameController::myHistory()`
- Service principal: `GameApplicationService::finishedGamesForUser()`
- Projection: `GamePayloadBuilder::buildHistoryItem()`
- Paramètres:
  - `page`
  - `perPage`

### `GET /api/games/rejoin`

- Contrôleur: `GameController::rejoin()`
- Service principal: `GameApplicationService::rejoin()`
- Projection:
  - `buildFull()`
  - `buildResult()`
- Usage: reprise automatique de la dernière partie active

### `GET /api/games/{id}`

- Contrôleur: `GameController::show()`
- Service principal: `GameApplicationService::show()`
- Projection:
  - `buildFull()`
  - `buildResult()`
- Usage: consultation détaillée d'une partie

### `GET /api/games/{id}/sync`

- Contrôleur: `GameController::sync()`
- Service principal: `GameApplicationService::sync()`
- Projection:
  - `buildFull()`
  - `buildResult()`
- Usage: resynchronisation complète côté client

### `GET /api/games/{id}/poll`

- Contrôleur: `GameController::poll()`
- Service principal: `GameApplicationService::poll()`
- Projection:
  - si inchangé: `game=null`
  - sinon: `buildFull()` + `buildResult()`
- Usage: fallback polling

### `POST /api/games/{id}/ack`

- Contrôleur: `GameController::ack()`
- Service principal: `GameApplicationService::acknowledgeState()`
- Usage: confirmation explicite de la dernière version appliquée côté client

### `POST /api/games/{id}/join`

- Contrôleur: `GameController::join()`
- Service principal: `GameApplicationService::join()`
- Projection:
  - `buildSummary()`
  - `buildResult()`
- Usage: rejoindre une partie `waiting`

### `POST /api/games/{id}/cancel`

- Contrôleur: `GameController::cancel()`
- Service principal: `GameApplicationService::cancel()`
- Usage: annuler une partie en attente

### `POST /api/games/{id}/play`

- Contrôleur: `MoveController::play()`
- Service principal: `MoveApplicationService::play()`
- Projection:
  - `buildFull()`
  - `buildResult()`
- Usage: jouer une carte

## Profil et classement

### `GET /api/profile/stats`

- Contrôleur: `ProfileController::stats()`
- Service principal: `ProfileStatsService::buildForUser()`
- Usage: profil statistique détaillé

### `GET /api/ranking`

- Contrôleur: `RankingController::index()`
- Repository principal: `UserRepository::findLeaderboard()`
- Usage: top 20 des joueurs par crédits

## Ordre de responsabilité dans les routes

En pratique, la plupart des routes suivent ce schéma:

1. contrôleur
2. service applicatif
3. repository(s) / moteur métier
4. éventuelle publication Mercure
5. projection via `GamePayloadBuilder`
6. réponse JSON

## Gestion des erreurs par route

La majorité des routes métiers utilisent `ApiException`.

Le contrôleur:

- attrape `ApiException`
- appelle `errorResponse()`
- renvoie:
  - `error.code`
  - `error.message`
  - `error.details` si disponible

Exceptions:

- certaines validations simples sont faites directement avant appel service
- par exemple JSON invalide, types invalides, pagination invalide
