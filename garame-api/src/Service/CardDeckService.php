<?php
// src/Service/CardDeckService.php

namespace App\Service;

/**
 * Gère le paquet de 23 cartes du jeu Garame.
 * Valeurs : 3 à 8 — Couleurs : H, D, C, S — Exception : 8S retiré.
 */
class CardDeckService
{
    // Ordre croissant des valeurs
    public const VALUES = [3, 4, 5, 6, 7, 8];
    public const SUITS  = ['H', 'D', 'C', 'S']; // Heart, Diamond, Club, Spade

    // La carte retirée du jeu
    private const REMOVED_CARD = ['value' => 8, 'suit' => 'S'];

    /**
     * Génère le paquet complet de 23 cartes mélangées.
     *
     * @return array<int, array{value: int, suit: string}>
     */
    public function generateShuffledDeck(): array
    {
        $deck = [];

        foreach (self::SUITS as $suit) {
            foreach (self::VALUES as $value) {
                // On retire le 8 de pique
                if ($value === self::REMOVED_CARD['value'] && $suit === self::REMOVED_CARD['suit']) {
                    continue;
                }
                $deck[] = ['value' => $value, 'suit' => $suit];
            }
        }

        // Vérification de cohérence
        assert(count($deck) === 23, 'Le paquet doit contenir exactement 23 cartes.');

        shuffle($deck);

        return $deck;
    }

    /**
     * Distribue 5 cartes à chaque joueur.
     * Retourne [main_joueur1, main_joueur2], les 3 cartes restantes sont ignorées.
     *
     * @return array{0: array, 1: array}
     */
    public function deal(): array
    {
        $deck = $this->generateShuffledDeck();

        $hand1 = array_slice($deck, 0, 5);
        $hand2 = array_slice($deck, 5, 5);
        // Les cartes 10-12 (index) ne sont pas utilisées

        return [
            $this->sortHand($hand1),
            $this->sortHand($hand2),
        ];
    }

    /**
     * Trie une main par valeur croissante (pour l'affichage).
     *
     * @param array $hand
     * @return array
     */
    public function sortHand(array $hand): array
    {
        usort($hand, fn($a, $b) => $a['value'] <=> $b['value']);
        return $hand;
    }

    /**
     * Calcule la somme des valeurs d'une main.
     */
    public function sumHand(array $hand): int
    {
        return array_sum(array_column($hand, 'value'));
    }

    /**
     * Vérifie la condition "Moins de 21" : somme ≤ 21.
     */
    public function isMoinsVingtEtUn(array $hand): bool
    {
        return $this->sumHand($hand) <= 21;
    }

    /**
     * Vérifie la condition "Three 7" : exactement 3 cartes de valeur 7.
     */
    public function isThreeSeven(array $hand): bool
    {
        $count = count(array_filter($hand, fn($card) => $card['value'] === 7));
        return $count === 3;
    }

    /**
     * Vérifie qu'une carte appartient bien au paquet Garame.
     */
    public function isValidCard(int $value, string $suit): bool
    {
        if (!in_array($value, self::VALUES, true)) return false;
        if (!in_array($suit, self::SUITS, true)) return false;
        if ($value === self::REMOVED_CARD['value'] && $suit === self::REMOVED_CARD['suit']) return false;

        return true;
    }

    /**
     * Représentation lisible d'une carte pour les logs/debug.
     */
    public function cardToString(int $value, string $suit): string
    {
        $suitSymbols = ['H' => '♥', 'D' => '♦', 'C' => '♣', 'S' => '♠'];
        return $value . ($suitSymbols[$suit] ?? $suit);
    }
}
