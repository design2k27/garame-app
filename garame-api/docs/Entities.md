# Modèle de données

Cette note décrit les entités Doctrine et leur rôle métier.

## Vue d'ensemble

Le domaine principal repose sur six entités:

- `User`
- `Game`
- `GamePlayer`
- `Round`
- `Move`
- `GameResult`

## User

Fichier: `src/Entity/User.php`

Représente un compte authentifiable.

### Champs clés

- `id`: UUID
- `username`: pseudo unique
- `email`: identifiant unique de connexion
- `passwordHash`: mot de passe hashé
- `roles`: rôles Symfony
- `gamesPlayed`: nombre total de parties jouées
- `gamesWon`: nombre total de parties gagnées
- `credits`: score de classement actuel
- `createdAt`: date de création

### Rôle métier

- identifie un joueur
- sert de propriétaire d'une session JWT
- porte les compteurs globaux du profil
- est lié aux parties via `GamePlayer`

### Particularités

- `getUserIdentifier()` retourne l'email
- les compteurs sont mis à jour à la fin d'une partie via `GameEngine::finalizeGame()`

## Game

Fichier: `src/Entity/Game.php`

Représente l'agrégat principal d'une partie.

### Champs clés

- `id`: UUID
- `status`: `waiting`, `playing`, `finished`
- `currentRound`: numéro du pli courant
- `stateVersion`: version d'état monotone
- `currentLeader`: joueur qui doit ouvrir le pli courant
- `winner`: vainqueur final
- `winType`: type de victoire final
- `startedAt`: date de création
- `endedAt`: date de fin
- `isMatchmakingQueue`: distingue une file matchmaking d'une partie lobby classique

### Relations

- `players`: collection de `GamePlayer`
- `rounds`: collection de `Round`
- `result`: `GameResult` final

### Rôle métier

- agrège l'état courant visible par les clients
- centralise le statut de la partie
- porte la version d'état utilisée pour la synchronisation

### Particularités

- `startedAt` est initialisé en `PrePersist`
- une file matchmaking est aussi stockée comme `Game`, avec `status=waiting` et `isMatchmakingQueue=true`

## GamePlayer

Fichier: `src/Entity/GamePlayer.php`

Table de jonction enrichie entre `Game` et `User`.

### Champs clés

- `game`
- `user`
- `position`: `1` ou `2`
- `hand`: main du joueur en JSON
- `tricksWon`: nombre de plis remportés
- `lastAckedStateVersion`: dernière version confirmée par le client
- `lastSeenAt`: dernier passage client connu

### Rôle métier

- permet de représenter un utilisateur dans le contexte d'une partie
- stocke les données privées spécifiques à la partie
- sépare bien les stats globales (`User`) et l'état temporaire de partie

### Particularités

- contrainte d'unicité `(game_id, user_id)`
- la main n'est jamais exposée à l'adversaire
- `removeCardFromHand()` retire une carte après un coup validé

## Round

Fichier: `src/Entity/Round.php`

Représente un pli.

### Champs clés

- `number`: numéro du pli, de 1 à 5
- `leader`: joueur qui ouvre le pli
- `winner`: gagnant du pli
- `winType`: renseigné uniquement au dernier pli si besoin
- `playedAt`: date de résolution

### Relations

- `game`
- `moves`: deux coups maximum, ordonnés par `playOrder`

### Rôle métier

- matérialise l'unité de jeu élémentaire
- porte la résolution intermédiaire d'une partie

### Particularités

- `getLeaderMove()` et `getResponderMove()` facilitent l'accès aux deux coups attendus
- un pli est complet lorsque `moves->count() === 2`

## Move

Fichier: `src/Entity/Move.php`

Représente une carte jouée dans un pli.

### Champs clés

- `round`
- `user`
- `playOrder`: `1` pour le meneur, `2` pour le répondant
- `cardValue`
- `cardSuit`
- `playedAt`

### Rôle métier

- capture un coup validé par le moteur
- sert à reconstruire l'état détaillé d'un pli

### Particularités

- `playedAt` est défini automatiquement au persist
- la validation de cohérence métier complète n'est pas dans l'entité mais dans `GameEngine`

## GameResult

Fichier: `src/Entity/GameResult.php`

Représente le résultat final immuable d'une partie.

### Champs clés

- `game`
- `winner`
- `loser`
- `winType`
- `stakeMultiplier`
- `createdAt`

### Rôle métier

- sert d'historique stable après finalisation
- alimente le profil et le leaderboard

### Particularités

- `stakeMultiplier=2` pour un `korat`
- créé au moment de `GameEngine::finalizeGame()`

## Enums

### GameStatus

Fichier: `src/Enum/GameStatus.php`

Valeurs:

- `waiting`
- `playing`
- `finished`

### WinType

Fichier: `src/Enum/WinType.php`

Valeurs:

- `moins_21`
- `three_seven`
- `match_simple`
- `korat`

## Relations simplifiées

```text
User 1---n GamePlayer n---1 Game
Game 1---n Round
Round 1---n Move
Game 1---1 GameResult
GameResult n---1 User (winner)
GameResult n---1 User (loser)
```

## Données privées vs données publiques

Quelques éléments sont volontairement privés ou contextualisés:

- `GamePlayer.hand`: visible uniquement au joueur courant
- `GamePlayer.lastAckedStateVersion`: exposée dans le payload joueur et adversaire pour la synchro
- `User.passwordHash`: jamais exposé

## Invariants métier importants

- une partie a au plus deux `GamePlayer`
- un utilisateur ne doit pas apparaître deux fois dans une même partie
- un pli contient au plus deux `Move`
- une partie terminée peut avoir un seul `GameResult`
- une partie active possède une `stateVersion` utilisée pour la cohérence client
