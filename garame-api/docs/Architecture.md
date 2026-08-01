# Architecture de l'API Garame

Cette note décrit le fonctionnement interne du backend Garame au-delà du simple contrat HTTP.

## Vue d'ensemble

Le projet est une API Symfony 7.4 qui gère:

- authentification JWT
- matchmaking 1v1
- exécution des règles du jeu Garame
- synchronisation d'état client
- publication d'événements temps réel via Mercure
- statistiques joueur et leaderboard

La structure générale suit cette logique:

- `Controller`: couche HTTP, validation des entrées simples, codes de réponse JSON
- `Service`: logique applicative et métier
- `Repository`: chargement Doctrine optimisé pour les cas d'usage métier
- `Entity`: état persistant du domaine

## Couches principales

### 1. Couche HTTP

Les contrôleurs convertissent les requêtes HTTP en appels de services:

- `AuthController`: inscription, connexion, utilisateur courant
- `GameController`: création, matchmaking, consultation, rejoin, sync, poll, ack, historique
- `MoveController`: jouer une carte
- `ProfileController`: statistiques du joueur connecté
- `RankingController`: classement global
- `HomeController`: pages publiques et healthcheck

Les contrôleurs n'implémentent pas les règles du jeu. Ils orchestrent surtout:

- le parsing JSON
- la validation de type et de paramètres
- la conversion des exceptions métier en réponses API
- la sérialisation finale des payloads

### 2. Couche applicative

Les services applicatifs portent la logique d'orchestration:

- `GameApplicationService`: cycle de vie des parties, matchmaking, reprise, synchronisation
- `MoveApplicationService`: traitement d'un coup et diffusion temps réel
- `ProfileStatsService`: agrégation de statistiques profil

Cette couche décide:

- quelles entités charger
- quelles règles métier appeler
- quand créer ou annuler une partie
- quand incrémenter `stateVersion`
- quand publier un événement Mercure

### 3. Couche métier

Le coeur métier du jeu est surtout dans:

- `GameEngine`
- `CardDeckService`

`GameEngine` contient les règles Garame réellement implémentées:

- distribution des cartes
- détection des victoires immédiates
- validation des tours
- obligation de suivre la couleur
- résolution d'un pli
- résolution du dernier pli
- détection du `korat`
- finalisation d'une partie et mise à jour des stats

`CardDeckService` encapsule la connaissance du paquet:

- cartes autorisées
- suppression du `8S`
- mélange et distribution
- vérification d'une carte valide
- détection de `three_seven`
- détection de `moins_21`

### 4. Couche de projection / payload

`GamePayloadBuilder` transforme les entités Doctrine en réponses JSON adaptées au client.

Il distingue plusieurs niveaux de vue:

- `buildSummary()`: vue courte d'une partie
- `buildFull()`: vue complète d'une partie pour un participant
- `buildResult()`: résultat courant calculé à partir du moteur ou de l'état
- `buildHistoryItem()`: projection pour l'historique

Cette séparation évite de disperser la logique de sérialisation dans les contrôleurs.

## Flux métier principaux

### Authentification

1. `POST /api/auth/register`
2. le contrôleur valide JSON, champs requis et unicité
3. l'entité `User` est validée via le validator Symfony
4. le mot de passe est hashé
5. l'utilisateur est persisté
6. un JWT est généré et renvoyé

Pour `login`, le flux est similaire mais sans persistance.

### Création d'une partie directe

1. `POST /api/games`
2. `GameApplicationService::create()`
3. verrou pessimiste sur l'utilisateur
4. vérification qu'il n'a pas déjà une partie active
5. création de `Game`
6. création de `GamePlayer` position 1
7. flush
8. projection de réponse via `GamePayloadBuilder`

### Matchmaking

1. `POST /api/games/matchmaking`
2. `GameApplicationService::matchmaking()`
3. transaction Doctrine
4. verrou pessimiste sur l'utilisateur
5. nettoyage des files expirées
6. si l'utilisateur a déjà une file non expirée: retour `queued`
7. sinon recherche d'une file disponible
8. s'il n'y en a pas: création d'une file matchmaking
9. sinon jointure à la partie trouvée
10. démarrage du moteur de jeu via `GameEngine::startGame()`
11. incrément de `stateVersion`
12. publication `game_started` via Mercure

Le matchmaking prévoit des retries sur exceptions DB retryables ou contraintes d'unicité.

### Jeu d'une carte

1. `POST /api/games/{id}/play`
2. validation JSON et champs minimaux dans `MoveController`
3. `MoveApplicationService::play()`
4. chargement de la partie avec ses relations
5. contrôle d'accès du joueur
6. vérification optionnelle de `clientStateVersion`
7. appel à `GameEngine::playCard()`
8. selon le résultat:
   - `waiting`
   - `round_complete`
   - `finished`
9. incrément de `stateVersion`
10. publication Mercure correspondante
11. réponse full game au client

### Temps réel fiable

Le backend implémente un schéma à version d'état monotone:

- `Game.stateVersion`: version serveur globale
- `GamePlayer.lastAckedStateVersion`: version confirmée par un client

Routes associées:

- `GET /api/games/{id}/sync`
- `GET /api/games/{id}/poll`
- `POST /api/games/{id}/ack`

Objectif:

- détecter les désynchronisations client
- permettre un fallback polling si Mercure est indisponible
- conserver une trace de la dernière version effectivement ackée

### Fin de partie

Une partie peut se terminer:

- immédiatement à la distribution
- après le 5e pli

Lors de la finalisation:

- `Game.status` passe à `finished`
- `winner`, `winType`, `endedAt` sont renseignés
- un `GameResult` est créé
- `gamesPlayed`, `gamesWon`, `credits` sont mis à jour

## Gestion des erreurs

L'API utilise un modèle d'erreur métier unifié:

- les services lèvent `ApiException` pour les erreurs contrôlées
- les contrôleurs convertissent ces exceptions en JSON standardisé
- les erreurs de validation d'entrée simples peuvent être gérées directement dans le contrôleur

Le format de référence est décrit dans [`API.md`](./API.md).

## Synchronisation de la persistance

Quelques points importants dans le code actuel:

- les services applicatifs utilisent des transactions Doctrine pour les opérations critiques
- certaines lectures utilisent des verrous pessimistes en environnement compatible
- `GameRepository::findWithFullRelations()` charge les relations nécessaires pour éviter des N+1
- `GameEngine` force parfois des `refresh()` pour relire l'état Doctrine à jour avant de poursuivre

## Temps réel et résilience

Mercure est intégré via `MercurePublisher`.

Événements publiés:

- `game_started`
- `card_played`
- `round_complete`
- `game_finished`

Le publisher ignore silencieusement les erreurs de publication Mercure, ce qui permet à la logique métier de continuer même si le hub est indisponible. La contrepartie est qu'il faut un fallback client via `poll`.

## Ce qui n'est pas encore dans cette architecture

Le code actuel ne couvre pas encore:

- refresh tokens
- MMR/ELO, saisons, ligues
- modération avancée
- audit log métier complet des coups
- rate limiting applicatif
- métriques et alerting

Cette documentation décrit donc un backend de jeu déjà structuré, mais encore en phase de consolidation avant une production plus exigeante.
