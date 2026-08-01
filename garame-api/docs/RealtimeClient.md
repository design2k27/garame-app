# Guide Client Temps Réel

Ce guide décrit le contrat recommandé côté frontend pour une expérience temps réel robuste.

## Objectif

- Utiliser Mercure quand disponible
- Basculer en polling si Mercure est indisponible
- Resynchroniser proprement après coupure
- Ack explicite des versions d'état appliquées

## Données importantes

- `game.stateVersion`: version d'état serveur (monotone)
- `event.stateVersion`: version d'état attachée aux événements Mercure
- `myAckedStateVersion`: dernière version confirmée par ce client

## Stratégie recommandée

1. Authentification JWT
2. `GET /api/games/rejoin`
3. Si `action=none`:
   - pas de partie active
4. Si `action=rejoined`:
   - stocker `currentVersion = game.stateVersion`
   - lancer abonnement Mercure `game/{id}`
5. À réception d'un event Mercure:
   - si `event.stateVersion <= currentVersion`: ignorer (duplicate/late)
   - sinon appeler `GET /api/games/{id}/sync?sinceVersion={currentVersion}`
   - si `inSync=false`, appliquer l'état complet renvoyé
   - mettre `currentVersion = serverVersion`
   - appeler `POST /api/games/{id}/ack` avec `stateVersion=currentVersion`
6. Si Mercure indisponible (timeout/retry WS/SSE):
   - passer en boucle `GET /api/games/{id}/poll?sinceVersion={currentVersion}`
   - si `changed=false`, attendre `pollAfterMs`
   - si `changed=true`, appliquer `game`, mettre `currentVersion=serverVersion`, puis `ack`
7. Lors d'une action joueur (`play`):
   - envoyer `clientStateVersion=currentVersion`
   - si `409 out_of_sync`, faire `sync`, mettre à jour UI, puis rejouer l'action si toujours valide

## Exemples

### Ack

```http
POST /api/games/{id}/ack
Content-Type: application/json
Authorization: Bearer <jwt>

{"stateVersion": 12}
```

### Poll

```http
GET /api/games/{id}/poll?sinceVersion=12
Authorization: Bearer <jwt>
```

### Play safe

```json
{
  "value": 6,
  "suit": "H",
  "clientStateVersion": 12
}
```

## Règles UI

- Toujours rendre l'UI depuis l'état serveur (`sync`/`poll`/`play` response)
- Ne jamais faire confiance à un état local non versionné
- Afficher un badge "resync" si `out_of_sync`
