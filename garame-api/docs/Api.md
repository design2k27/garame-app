# API Garame

Documentation de l'API HTTP implémentée dans ce dépôt.

## Vue d'ensemble

- Base API: `/api`
- Format: JSON
- Authentification: `Authorization: Bearer <jwt>`
- Règle d'accès actuelle:
  - Public: `POST /api/auth/register`, `POST /api/auth/login`, `GET /health`, `GET /`
  - JWT requis: toutes les autres routes `/api` (y compris `GET /api/auth/me`, `GET /api/ranking`, `GET /api/docs`)

## Contrat d'erreur

Toutes les erreurs applicatives utilisent ce format:

```json
{
  "error": {
    "code": "rule_violation",
    "message": "Vous ne possédez pas cette carte."
  }
}
```

Avec détails éventuels:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "La validation a échoué.",
    "details": {
      "email": "This value is not a valid email address."
    }
  }
}
```

Codes d'erreur applicatifs actuellement utilisés:

- `invalid_json`
- `missing_required_fields`
- `email_already_used`
- `username_already_taken`
- `validation_failed`
- `missing_credentials`
- `invalid_credentials`
- `unauthenticated`
- `active_game_exists`
- `game_not_found`
- `game_access_denied`
- `game_unavailable`
- `game_full`
- `already_in_game`
- `game_cannot_be_cancelled`
- `invalid_page`
- `invalid_per_page`
- `invalid_state_version`
- `missing_card_payload`
- `invalid_card_payload`
- `rule_violation`
- `out_of_sync`

## Authentification

### `POST /api/auth/register`

Crée un compte et retourne un JWT.

Body:

```json
{
  "username": "alice",
  "email": "alice@test.com",
  "password": "secret123"
}
```

Réponse `201`:

```json
{
  "token": "jwt-token",
  "user": {
    "id": "uuid",
    "username": "alice",
    "email": "alice@test.com",
    "credits": 0,
    "gamesPlayed": 0,
    "gamesWon": 0,
    "createdAt": "2026-04-22T10:00:00+00:00"
  }
}
```

### `POST /api/auth/login`

Retourne un JWT pour un utilisateur existant.

Body:

```json
{
  "email": "alice@test.com",
  "password": "secret123"
}
```

Réponse `200`: même structure que register.

### `GET /api/auth/me`

Retourne l'utilisateur authentifié.

Réponse `200`:

```json
{
  "user": {
    "id": "uuid",
    "username": "alice",
    "email": "alice@test.com",
    "credits": 0,
    "gamesPlayed": 0,
    "gamesWon": 0,
    "createdAt": "2026-04-22T10:00:00+00:00"
  }
}
```

## Santé et docs

### `GET /health`

Endpoint public de healthcheck.

Réponse `200`:

```json
{
  "status": "ok"
}
```

### `GET /api/docs`

Documentation OpenAPI générée par API Platform.

- Route existante: `/api/docs.{_format}`
- Actuellement protégée par JWT (car sous `/api`)

## Contrat des réponses Game

Les endpoints unitaires de partie renvoient:

```json
{
  "action": "created|queued|joined|rejoined|fetched|synced|played",
  "game": { "...": "..." },
  "result": {
    "status": "waiting|playing|round_complete|finished",
    "winner": null,
    "winType": null,
    "roundWinner": null,
    "nextLeader": null,
    "nextRound": null
  }
}
```

Les endpoints de liste renvoient:

```json
{
  "action": "listed",
  "games": []
}
```

ou pour l'historique:

```json
{
  "action": "listed",
  "items": [],
  "pagination": {
    "page": 1,
    "perPage": 10,
    "total": 0,
    "totalPages": 1
  }
}
```

## Routes Game

Toutes les routes ci-dessous nécessitent un JWT.

### `GET /api/games/open`

Liste les parties en attente (`waiting`).

### `POST /api/games`

Crée une partie `waiting`.

Réponse: `201`, `action=created`.

### `POST /api/games/matchmaking`

- Rejoint une partie `waiting` disponible, sinon crée une nouvelle partie.
- `201` si création (`action=created`)
- `200` si déjà en file (`action=queued`)
- `200` si jointure (`action=joined`)
- Le statut peut être `playing` ou `finished` (victoire immédiate possible au démarrage)
- Les entrées de file matchmaking expirent automatiquement après timeout serveur

### `POST /api/games/matchmaking/cancel`

Annule la recherche matchmaking en attente pour l'utilisateur courant.

Réponse `200`:

```json
{
  "action": "cancelled",
  "gameId": "uuid ou null"
}
```

### `GET /api/games/my/active`

Retourne les parties utilisateur en `waiting` ou `playing`.

### `GET /api/games/my/history?page=1&perPage=10`

Historique des parties `finished`.

- `page >= 1`
- `1 <= perPage <= 50`

### `GET /api/games/{id}`

Retourne l'état complet d'une partie si l'utilisateur en fait partie.

Exemple de `game` (champs principaux):

```json
{
  "id": "uuid",
  "status": "playing",
  "currentRound": 2,
  "currentLeader": "alice",
  "winType": null,
  "winner": null,
  "myHand": [{ "value": 7, "suit": "D" }],
  "myTricksWon": 1,
  "opponent": {
    "id": "uuid",
    "username": "bob",
    "credits": 40,
    "tricksWon": 0,
    "cardsLeft": 1
  },
  "currentRoundData": {
    "number": 2,
    "leader": "alice",
    "moves": []
  },
  "rounds": [],
  "startedAt": "2026-04-22T10:00:00+00:00",
  "endedAt": null
}
```

### `GET /api/games/rejoin`

Reprise automatique de la partie active la plus récente (`waiting` ou `playing`).

Réponse sans partie active:

```json
{
  "action": "none",
  "game": null,
  "result": null
}
```

Réponse avec partie active:

```json
{
  "action": "rejoined",
  "game": {
    "id": "uuid",
    "stateVersion": 3
  },
  "result": {
    "status": "playing"
  }
}
```

### `GET /api/games/{id}/sync?sinceVersion=3`

Resynchronise l'état côté client après coupure.

- `sinceVersion` optionnel, entier `>= 1`
- Si `sinceVersion` est égal à `game.stateVersion`, `inSync=true`

Réponse:

```json
{
  "action": "synced",
  "inSync": true,
  "serverVersion": 3,
  "game": {
    "id": "uuid",
    "stateVersion": 3
  },
  "result": {
    "status": "playing"
  }
}
```

### `GET /api/games/{id}/poll?sinceVersion=3`

Fallback polling lorsque le flux Mercure est indisponible.

- `sinceVersion` requis, entier `>= 0`
- `changed=false`: pas de nouvel état, repoller après `pollAfterMs`
- `changed=true`: renvoie l'état complet à appliquer côté client

### `POST /api/games/{id}/ack`

ACK client pour confirmer la dernière version d'état appliquée côté client.

Body:

```json
{
  "stateVersion": 3
}
```

Réponse:

```json
{
  "action": "acked",
  "gameId": "uuid",
  "ackedStateVersion": 3,
  "serverVersion": 3,
  "inSync": true
}
```

### `POST /api/games/{id}/join`

Rejoint une partie `waiting`.

### `POST /api/games/{id}/cancel`

Annule une partie `waiting` (participant requis).

Réponse `200`:

```json
{
  "action": "cancelled",
  "gameId": "uuid"
}
```

### `POST /api/games/{id}/play`

Joue une carte.

Body:

```json
{
  "value": 6,
  "suit": "H",
  "clientStateVersion": 3
}
```

Validation:

- JSON valide
- `value` entier, `suit` chaîne
- carte appartenant au paquet Garame
- carte possédée par le joueur
- tour du joueur
- obligation de suivre la couleur (sauf dernière carte)
- si `clientStateVersion` est fourni et différent de la version serveur: erreur `out_of_sync` (`409`)

`result.status` possibles:

- `waiting`: première carte du pli posée
- `round_complete`: pli terminé
- `finished`: partie terminée

## Classement

### `GET /api/ranking`

Retourne le leaderboard (20 premiers) trié par crédits décroissants.

Réponse `200`:

```json
{
  "action": "listed",
  "items": [
    {
      "rank": 1,
      "id": "uuid",
      "username": "alice",
      "credits": 30,
      "gamesPlayed": 5,
      "gamesWon": 3
    }
  ]
}
```

Règles crédits:

- victoire `match_simple`: `+10`
- défaite `match_simple`: `-10`
- victoire `korat`: `+20`
- défaite `korat`: `-20`

## Profil joueur

### `GET /api/profile/stats`

Retourne les statistiques détaillées du joueur connecté:

- résumé global (`totalGames`, `wins`, `losses`, `winRate`)
- répartition par type de victoire (`byWinType`)
- série courante (`streak`)
- 20 derniers matchs (`recent`)

Exemple:

```json
{
  "action": "fetched",
  "stats": {
    "summary": {
      "totalGames": 12,
      "wins": 8,
      "losses": 4,
      "winRate": 66.67,
      "credits": 40,
      "gamesPlayed": 12,
      "gamesWon": 8
    },
    "byWinType": {
      "three_seven": { "wins": 2, "losses": 1, "total": 3 },
      "moins_21": { "wins": 1, "losses": 0, "total": 1 },
      "match_simple": { "wins": 4, "losses": 3, "total": 7 },
      "korat": { "wins": 1, "losses": 0, "total": 1 }
    },
    "streak": {
      "type": "win",
      "count": 3
    },
    "recent": [
      {
        "gameId": "uuid",
        "resultId": "uuid",
        "didWin": true,
        "winType": "korat",
        "stakeMultiplier": 2,
        "creditsDelta": 20,
        "opponent": {
          "id": "uuid",
          "username": "bob"
        },
        "playedAt": "2026-04-24T12:00:00+00:00"
      }
    ]
  }
}
```

## Règles Garame implémentées

### Paquet

- 23 cartes
- Valeurs: `3, 4, 5, 6, 7, 8`
- Couleurs: `H`, `D`, `C`, `S`
- `8S` retiré du paquet

### Distribution

- 5 cartes par joueur
- 3 cartes restantes ignorées
- mains triées par valeur croissante

### Victoires immédiates (ordre)

1. `three_seven`
2. `moins_21`

Détails:

- `three_seven`: exactement 3 cartes de valeur `7`
- `moins_21`: somme des 5 cartes `<= 21`
- si les deux ont `three_seven`, le joueur position 1 gagne
- si les deux ont `moins_21`, somme la plus faible gagne
- si égalité de somme `<= 21`, joueur position 1 gagne

### Plis

- le meneur joue en premier
- le second joueur suit la couleur s'il peut
- exception: s'il ne reste qu'une carte au second joueur, il peut la jouer
- si le second joueur ne suit pas, le meneur gagne le pli
- si les deux suivent, la valeur la plus haute gagne
- en cas d'égalité de valeur, le meneur gagne

### Fin de partie (5e pli)

- `match_simple`: vainqueur du pli
- `korat`: le meneur gagne en jouant un `3` au dernier pli

## Mercure (temps réel)

Topic publié:

```text
game/{gameId}
```

Événements publiés:

- `game_started`
- `card_played`
- `round_complete`
- `game_finished`

Exemple `card_played`:

```json
{
  "event": "card_played",
  "eventId": 4,
  "stateVersion": 4,
  "player": "alice",
  "card": { "value": 6, "suit": "H" },
  "leader": "alice"
}
```
