# Services et responsabilités

Cette note décrit les services clés du backend et leur responsabilité précise.

## Vue d'ensemble

Les services ne jouent pas tous le même rôle. On peut les regrouper en quatre familles:

- services applicatifs
- services métier
- services de projection
- services d'infrastructure légère

## Services applicatifs

### GameApplicationService

Fichier: `src/Service/GameApplicationService.php`

Responsable du cycle de vie d'une partie hors coups individuels.

### Responsabilités

- créer une partie directe
- gérer le matchmaking
- annuler une file de matchmaking
- rejoindre une partie
- annuler une partie en attente
- retrouver les parties actives d'un joueur
- paginer l'historique des parties terminées
- exposer le `rejoin`
- exposer `sync`, `poll` et `ack`

### Points techniques notables

- utilise des transactions Doctrine
- verrouille l'utilisateur courant pour les opérations sensibles
- nettoie les files matchmaking expirées
- applique des retries sur certaines erreurs concurrentes
- incrémente `stateVersion` lors du démarrage effectif d'une partie

### Pourquoi il existe

Il sert de façade unique pour les actions de partie qui ne sont pas un coup de carte.

### MoveApplicationService

Fichier: `src/Service/MoveApplicationService.php`

Responsable du traitement d'un coup.

### Responsabilités

- charger la partie avec ses relations utiles
- vérifier l'accès du joueur
- détecter un client désynchronisé
- appeler `GameEngine::playCard()`
- convertir les erreurs métier du moteur en `ApiException`
- incrémenter `stateVersion`
- publier l'événement Mercure adapté

### Pourquoi il existe

Il sépare le protocole HTTP et la logique de diffusion temps réel du coeur du moteur.

### ProfileStatsService

Fichier: `src/Service/ProfileStatsService.php`

Responsable de la construction du profil statistique du joueur.

### Responsabilités

- calcul du winrate
- agrégation par type de victoire
- récupération des 20 dernières parties
- calcul de la streak courante

### Pourquoi il existe

Il encapsule la logique de lecture analytique au lieu de l'étaler dans le contrôleur.

## Services métier

### GameEngine

Fichier: `src/Service/GameEngine.php`

C'est le coeur du jeu.

### Responsabilités

- démarrage de partie
- distribution des mains
- détection des victoires immédiates
- validation des coups
- contrôle du tour
- contrôle de la couleur demandée
- résolution des plis
- détection de `korat`
- finalisation de partie
- mise à jour des statistiques et crédits

### Règle pratique

Si une logique concerne la vérité métier du jeu Garame, elle doit être ici ou dans un service métier très proche.

### CardDeckService

Fichier: `src/Service/CardDeckService.php`

Responsable de la connaissance statique du paquet.

### Responsabilités

- générer le paquet
- exclure `8S`
- mélanger
- distribuer
- trier une main
- calculer la somme d'une main
- vérifier `moins_21`
- vérifier `three_seven`
- valider l'existence d'une carte

### Pourquoi il existe

Il évite de dupliquer la logique "quelles cartes existent" dans plusieurs couches.

## Services de projection

### GamePayloadBuilder

Fichier: `src/Service/GamePayloadBuilder.php`

Responsable des payloads JSON retournés à l'API.

### Responsabilités

- construire une vue summary d'une partie
- construire une vue full participant-aware
- construire un bloc `result`
- construire des éléments d'historique

### Point important

La sérialisation n'est pas symétrique pour tous les utilisateurs:

- le joueur courant reçoit `myHand`
- l'adversaire n'est exposé qu'avec `cardsLeft`, `tricksWon`, etc.

## Services d'infrastructure légère

### MercurePublisher

Fichier: `src/Service/MercurePublisher.php`

Responsable de l'émission d'événements Mercure.

### Responsabilités

- publier `game_started`
- publier `card_played`
- publier `round_complete`
- publier `game_finished`

### Particularité

Les erreurs de publication sont attrapées et ignorées silencieusement.
La continuité du métier prime donc sur la disponibilité du temps réel.

### HomeStatusService

Fichier: `src/Service/HomeStatusService.php`

Responsable de la page d'accueil technique.

### Responsabilités

- tester l'état de dépendances comme la base, Mercure ou le transport async
- produire une vue synthétique pour la page `/`

## Comment les services collaborent

### Exemple: démarrage d'une partie via matchmaking

1. `GameController::matchmaking()`
2. `GameApplicationService::matchmaking()`
3. `GameRepository` et `UserRepository` pour charger/verrouiller
4. `GameEngine::startGame()`
5. `MercurePublisher::publishGameStarted()`
6. `GamePayloadBuilder` pour la réponse

### Exemple: coup joué

1. `MoveController::play()`
2. `MoveApplicationService::play()`
3. `GameRepository` + `GamePlayerRepository`
4. `GameEngine::playCard()`
5. `MercurePublisher`
6. `GamePayloadBuilder`

## Ce qui n'est pas encore factorisé

Le code actuel reste simple et lisible, mais certaines évolutions futures pourraient justifier:

- un service dédié au leaderboard
- un service de sécurité applicative
- un service d'audit métier
- un service de notifications produit

Pour l'état actuel du projet, la séparation existante reste cohérente.
