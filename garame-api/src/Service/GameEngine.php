<?php
// src/Service/GameEngine.php

namespace App\Service;

use App\Entity\Game;
use App\Entity\GamePlayer;
use App\Entity\GameResult;
use App\Entity\Move;
use App\Entity\Round;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Enum\WinType;
use App\Repository\GamePlayerRepository;
use App\Repository\RoundRepository;
use Doctrine\ORM\EntityManagerInterface;

class GameEngine
{
    private const BASE_RANKING_CREDITS = 10;

    public function __construct(
        private readonly CardDeckService      $deckService,
        private readonly EntityManagerInterface $em,
        private readonly GamePlayerRepository  $gamePlayerRepository,
        private readonly RoundRepository       $roundRepository,
    ) {}

    // -------------------------------------------------------------------------
    // 1. DÉMARRAGE DE LA PARTIE
    // -------------------------------------------------------------------------

    /**
     * Démarre la partie : distribue les cartes, vérifie les conditions immédiates,
     * crée le premier pli si aucune victoire immédiate.
     *
     * @return array{status: string, winner?: User, winType?: WinType}
     */
    public function startGame(Game $game): array
    {
        [$hand1, $hand2] = $this->deckService->deal();

        $players = $this->gamePlayerRepository->findByGame($game);
        $player1 = $players[0]; // position 1 = dealer / meneur initial
        $player2 = $players[1];

        $player1->setHand($hand1);
        $player2->setHand($hand2);

        $game->setStatus(GameStatus::PLAYING);
        $game->setCurrentLeader($player1->getUser());

        $this->em->flush();

        // --- Vérification des victoires immédiates ---
        $immediateResult = $this->checkImmediateVictory($game, $player1, $player2);
        if ($immediateResult !== null) {
            return $immediateResult;
        }

        // --- Aucune victoire immédiate : on crée le premier pli ---
        $this->createNewRound($game, $player1->getUser());

        return ['status' => 'playing'];
    }

    // -------------------------------------------------------------------------
    // 2. VICTOIRES IMMÉDIATES
    // -------------------------------------------------------------------------

    /**
     * Vérifie les conditions de victoire immédiate après distribution.
     * Priorité : Three 7 > Moins 21.
     * Si les deux joueurs remplissent la même condition, le joueur 1 gagne.
     *
     * @return array{status: string, winner: User, winType: WinType}|null
     */
    public function checkImmediateVictory(Game $game, GamePlayer $player1, GamePlayer $player2): ?array
    {
        $hand1 = $player1->getHand();
        $hand2 = $player2->getHand();

        // Three 7 — priorité absolue
        $p1Three7 = $this->deckService->isThreeSeven($hand1);
        $p2Three7 = $this->deckService->isThreeSeven($hand2);

        if ($p1Three7 || $p2Three7) {
            $winner = $p1Three7 ? $player1->getUser() : $player2->getUser();
            $loser  = $p1Three7 ? $player2->getUser() : $player1->getUser();
            $this->finalizeGame($game, $winner, $loser, WinType::THREE_SEVEN);
            return ['status' => 'finished', 'winner' => $winner, 'winType' => WinType::THREE_SEVEN];
        }

        // Moins 21
        $p1Moins21 = $this->deckService->isMoinsVingtEtUn($hand1);
        $p2Moins21 = $this->deckService->isMoinsVingtEtUn($hand2);

        if ($p1Moins21 || $p2Moins21) {
            // Si les deux ont ≤ 21, celui avec la somme la plus basse gagne
            if ($p1Moins21 && $p2Moins21) {
                $sum1 = $this->deckService->sumHand($hand1);
                $sum2 = $this->deckService->sumHand($hand2);
                $winner = ($sum1 <= $sum2) ? $player1->getUser() : $player2->getUser();
                $loser  = ($sum1 <= $sum2) ? $player2->getUser() : $player1->getUser();
            } else {
                $winner = $p1Moins21 ? $player1->getUser() : $player2->getUser();
                $loser  = $p1Moins21 ? $player2->getUser() : $player1->getUser();
            }
            $this->finalizeGame($game, $winner, $loser, WinType::MOINS_21);
            return ['status' => 'finished', 'winner' => $winner, 'winType' => WinType::MOINS_21];
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // 3. JOUER UNE CARTE
    // -------------------------------------------------------------------------

    /**
     * Valide et enregistre le coup d'un joueur.
     *
     * @return array{status: string, error?: string, winner?: User, winType?: WinType}
     */
    public function playCard(Game $game, User $user, int $value, string $suit): array
    {
        if (!$game->isPlaying()) {
            return ['status' => 'error', 'error' => 'La partie n\'est pas en cours.'];
        }

        // Recharger le game pour avoir le leader à jour
        $this->em->refresh($game);

        if ($game->getCurrentLeader() === null) {
            return ['status' => 'error', 'error' => 'Aucun meneur défini.'];
        }

        $round = $this->roundRepository->findCurrentRound($game);
        if (!$round) {
            return ['status' => 'error', 'error' => 'Aucun pli en cours.'];
        }

        // NE PAS refresh le round ici — on veut les moves en mémoire

        $playOrder = $this->getExpectedPlayOrder($round, $game, $user);
        if ($playOrder === null) {
            return ['status' => 'error', 'error' => 'Ce n\'est pas votre tour.'];
        }

        $gamePlayer = $this->gamePlayerRepository->findByGameAndUser($game, $user);
        if (!$gamePlayer) {
            return ['status' => 'error', 'error' => 'Joueur introuvable dans cette partie.'];
        }

        if (!$this->deckService->isValidCard($value, $suit)) {
            return ['status' => 'error', 'error' => 'Cette carte n\'appartient pas au paquet Garame.'];
        }

        if (!$this->gamePlayerRepository->playerHasCard($game, $user, $value, $suit)) {
            return ['status' => 'error', 'error' => 'Vous ne possédez pas cette carte.'];
        }

        if ($playOrder === 2) {
            $followError = $this->checkMustFollow($round, $gamePlayer, $suit);
            if ($followError) {
                return ['status' => 'error', 'error' => $followError];
            }
        }

        // Enregistrement du coup
        $move = new Move();
        $move->setRound($round);
        $move->setUser($user);
        $move->setPlayOrder($playOrder);
        $move->setCardValue($value);
        $move->setCardSuit($suit);

        $this->em->persist($move);
        $gamePlayer->removeCardFromHand($value, $suit);
        $this->em->flush();

        // Recharger le round APRÈS flush pour avoir le count exact depuis la DB
        $this->em->refresh($round);

        if ($round->getMoves()->count() === 2) {
            return $this->resolveRound($game, $round);
        }

        return ['status' => 'waiting'];
    }

    // -------------------------------------------------------------------------
    // 4. OBLIGATION DE SUIVRE LA COULEUR
    // -------------------------------------------------------------------------

    /**
     * Vérifie si le répondant doit suivre la couleur demandée.
     * Exception : dernière carte (plus qu'une carte en main).
     */
    private function checkMustFollow(Round $round, GamePlayer $responder, string $playedSuit): ?string
    {
        $leaderMove = $round->getLeaderMove();
        if (!$leaderMove) return null;

        $demandedSuit = $leaderMove->getCardSuit();

        // Dernière carte : pas d'obligation
        if (count($responder->getHand()) === 1) {
            return null;
        }

        // Le joueur suit une autre couleur alors qu'il en a une
        if ($playedSuit !== $demandedSuit && $responder->hasCardOfSuit($demandedSuit)) {
            return sprintf(
                'Vous devez jouer une carte de couleur %s.',
                $demandedSuit
            );
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // 5. RÉSOLUTION D'UN PLI
    // -------------------------------------------------------------------------

    /**
     * Détermine le gagnant du pli et prépare le suivant ou termine la partie.
     *
     * @return array{status: string, roundWinner: User, nextLeader?: User, nextRound?: int, winType?: WinType, winner?: User}
     */
    private function resolveRound(Game $game, Round $round): array
    {
        $leaderMove    = $round->getLeaderMove();
        $responderMove = $round->getResponderMove();

        $roundWinner = $this->determineRoundWinner($leaderMove, $responderMove, $round->getLeader());

        $round->setWinner($roundWinner);
        $round->setPlayedAt(new \DateTimeImmutable());

        $winnerPlayer = $this->gamePlayerRepository->findByGameAndUser($game, $roundWinner);
        $winnerPlayer?->incrementTricksWon();

        $game->setCurrentLeader($roundWinner);

        $this->em->flush();

        // Dernier pli (5e)
        if ($game->getCurrentRound() === 5) {
            return $this->resolveFinalRound($game, $round, $roundWinner);
        }

        // Préparer le pli suivant
        $game->incrementCurrentRound();
        $newRound = $this->createNewRound($game, $roundWinner);

        // Forcer Doctrine à recharger les entités depuis la DB
        $this->em->refresh($game);
        $this->em->refresh($newRound);

        $this->em->flush();

        return [
            'status' => 'round_complete',
            'roundWinner' => $roundWinner,
            'nextLeader' => $roundWinner,
            'nextRound' => $game->getCurrentRound(),
        ];
    }

    /**
     * Détermine le gagnant d'un pli selon les règles Garame.
     *
     * Règle : la carte la plus forte de la couleur demandée gagne.
     * Si le répondant ne suit pas → le meneur gagne.
     */
    private function determineRoundWinner(Move $leaderMove, Move $responderMove, User $leader): User
    {
        $demandedSuit = $leaderMove->getCardSuit();

        // Le répondant ne suit pas la couleur → le meneur gagne
        if ($responderMove->getCardSuit() !== $demandedSuit) {
            return $leader;
        }

        // Les deux suivent : la valeur la plus haute gagne
        if ($leaderMove->getCardValue() >= $responderMove->getCardValue()) {
            return $leader;
        }

        return $responderMove->getUser();
    }

    // -------------------------------------------------------------------------
    // 6. RÉSOLUTION DU DERNIER PLI (5e)
    // -------------------------------------------------------------------------

    /**
     * Résout le 5e et dernier pli.
     * Vérifie la condition Korat : meneur joue un 3, non battu → Korat.
     *
     * @return array{status: string, roundWinner: User, winner: User, winType: WinType, nextLeader?: User, nextRound?: int}
     */
    private function resolveFinalRound(Game $game, Round $round, User $roundWinner): array
    {
        $leaderMove    = $round->getLeaderMove();
        $responderMove = $round->getResponderMove();

        $winType = $this->detectKorat($leaderMove, $responderMove, $roundWinner, $round->getLeader())
            ? WinType::KORAT
            : WinType::MATCH_SIMPLE;

        $round->setWinType($winType);

        // Trouver le perdant — comparaison par ID
        $players = $this->gamePlayerRepository->findByGame($game);
        $loser   = $players[0]->getUser()->getId() === $roundWinner->getId()
            ? $players[1]->getUser()
            : $players[0]->getUser();

        $stakeMultiplier = $winType === WinType::KORAT ? 2 : 1;

        $this->finalizeGame($game, $roundWinner, $loser, $winType, $stakeMultiplier);

        return [
            'status'      => 'finished',
            'roundWinner' => $roundWinner,
            'winner'      => $roundWinner,
            'winType'     => $winType,
            'nextLeader'  => null,
            'nextRound'   => null,
        ];
    }

    /**
     * Détecte la condition Korat :
     * - C'est le 5e pli
     * - Le meneur a joué un 3
     * - Le répondant n'a pas pu battre avec une carte plus forte de la même couleur
     * - Le meneur a gagné le pli
     */
    private function detectKorat(Move $leaderMove, Move $responderMove, User $roundWinner, User $leader): bool
    {
        if ($roundWinner->getId() !== $leader->getId()) return false;
        if ($leaderMove->getCardValue() !== 3) return false;
        return true;
    }

    // -------------------------------------------------------------------------
    // 7. UTILITAIRES
    // -------------------------------------------------------------------------

    /**
     * Crée un nouveau pli et le persiste.
     */
    private function createNewRound(Game $game, User $leader): Round
    {
        $round = new Round();
        $round->setNumber($game->getCurrentRound());
        $round->setLeader($leader);
        $game->addRound($round);

        $this->em->persist($round);
        $this->em->flush();

        // Rafraîchir pour que les collections soient vides et propres
        $this->em->refresh($round);

        return $round;
    }

    /**
     * Termine la partie : met à jour Game, crée GameResult, met à jour les stats.
     */
    private function finalizeGame(
        Game    $game,
        User    $winner,
        User    $loser,
        WinType $winType,
        int     $stakeMultiplier = 1
    ): void {
        $game->setStatus(GameStatus::FINISHED);
        $game->setWinner($winner);
        $game->setWinType($winType);
        $game->setEndedAt(new \DateTimeImmutable());

        $result = new GameResult();
        $result->setGame($game);
        $result->setWinner($winner);
        $result->setLoser($loser);
        $result->setWinType($winType);
        $result->setStakeMultiplier($stakeMultiplier);

        $this->em->persist($result);

        // Mise à jour des stats joueurs
        $winnerPlayer = $this->gamePlayerRepository->findByGameAndUser($game, $winner);
        $winnerPlayer?->getUser()->incrementGamesWon();

        $winner->incrementGamesPlayed();
        $loser->incrementGamesPlayed();
        $winner->adjustCredits(self::BASE_RANKING_CREDITS * $stakeMultiplier);
        $loser->adjustCredits(-self::BASE_RANKING_CREDITS * $stakeMultiplier);

        $this->em->flush();
    }

    /**
     * Détermine l'ordre de jeu attendu pour un joueur dans le pli courant.
     * Retourne 1 si c'est le meneur, 2 si c'est le répondant, null si ce n'est pas son tour.
     */
    private function getExpectedPlayOrder(Round $round, Game $game, User $user): ?int
    {
        // Recharger les moves depuis la DB pour avoir le count exact
        $this->em->refresh($round);
        $movesCount = $round->getMoves()->count();

        $leaderId = $game->getCurrentLeader()?->getId();

        if ($movesCount === 0) {
            return $leaderId === $user->getId() ? 1 : null;
        }

        if ($movesCount === 1) {
            return $leaderId !== $user->getId() ? 2 : null;
        }

        return null;
    }
}
