# Documentation Technique Garame API

Ce dossier regroupe la documentation fonctionnelle et technique du backend Garame.

## Sommaire

- [`API.md`](./API.md): contrat HTTP public, endpoints, payloads, erreurs et règles exposées.
- [`RealtimeClient.md`](./RealtimeClient.md): contrat temps réel côté frontend, Mercure, `sync`, `poll`, `ack`.
- [`Architecture.md`](./Architecture.md): vue d'ensemble de l'architecture applicative et des flux métier principaux.
- [`Entities.md`](./Entities.md): description détaillée du modèle de données Doctrine.
- [`Services.md`](./Services.md): rôle et interactions des services métier.
- [`Routes.md`](./Routes.md): cartographie des routes HTTP avec contrôleurs, services et réponses.

## Lecture recommandée

Pour comprendre rapidement le projet:

1. Lire [`Architecture.md`](./Architecture.md)
2. Lire [`Entities.md`](./Entities.md)
3. Lire [`Services.md`](./Services.md)
4. Compléter avec [`Routes.md`](./Routes.md) et [`API.md`](./API.md)

## Portée

La documentation est alignée sur l'état courant du code dans `src/`.
Elle documente le comportement réellement implémenté, pas une cible produit future.
