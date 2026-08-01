<?php
// tests/Functional/GameFlowTest.php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\GamePlayer;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Enum\WinType;
use App\Service\CardDeckService;
use App\Service\GameEngine;
use App\Repository\GamePlayerRepository;
use App\Repository\RoundRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GameFlowTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private GameEngine $gameEngine;
    private CardDeckService $deckService;
    private GamePlayerRepository $gamePlayerRepository;
    private RoundRepository $roundRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em                   = $container->get(EntityManagerInterface::class);
        $this->gameEngine           = $container->get(GameEngine::class);
        $this->deckService          = $container->get(CardDeckService::class);
        $this->gamePlayerRepository = $container->get(GamePlayerRepository::class);
        $this->roundRepository      = $container->get(RoundRepository::class);

        // Nettoyer la base avant chaque test
        $this->em->getConnection()->executeStatement('DELETE FROM move');
        $this->em->getConnection()->executeStatement('DELETE FROM round');
        $this->em->getConnection()->executeStatement('DELETE FROM game_result');
        $this->em->getConnection()->executeStatement('DELETE FROM game_player');
        $this->em->getConnection()->executeStatement('DELETE FROM game');
        $this->em->getConnection()->executeStatement('DELETE FROM "user"');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUser(string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hashed');
        $this->em->persist($user);
        return $user;
    }

    private function createGame(User $user1, User $user2): Game
    {
        $game = new Game();
        $this->em->persist($game);

        $p1 = new GamePlayer();
        $p1->setGame($game)->setUser($user1)->setPosition(1);
        $this->em->persist($p1);

        $p2 = new GamePlayer();
        $p2->setGame($game)->setUser($user2)->setPosition(2);
        $this->em->persist($p2);

        $this->em->flush();
        return $game;
    }

    /**
     * Force une main spécifique à un joueur (pour tester des scénarios précis).
     */
    private function forceHands(Game $game, array $hand1, array $hand2): void
    {
        $players = $this->gamePlayerRepository->findByGame($game);
        $players[0]->setHand($hand1);
        $players[1]->setHand($hand2);
        $this->em->flush();
    }

    // -------------------------------------------------------------------------
    // Tests CardDeckService
    // -------------------------------------------------------------------------

    public function testDeckIsValid(): void
    {
        $deck = $this->deckService->generateShuffledDeck();
        $this->assertCount(23, $deck);

        foreach ($deck as $card) {
            $this->assertFalse(
                $card['value'] === 8 && $card['suit'] === 'S',
                '8♠ ne doit pas être dans le paquet'
            );
        }
    }

    public function testDealGivesTwoHandsOfFive(): void
    {
        [$hand1, $hand2] = $this->deckService->deal();
        $this->assertCount(5, $hand1);
        $this->assertCount(5, $hand2);

        // Pas de doublon entre les deux mains
        $all = array_merge($hand1, $hand2);
        $unique = array_unique(array_map(fn($c) => $c['value'].$c['suit'], $all));
        $this->assertCount(10, $unique);
    }

    // -------------------------------------------------------------------------
    // Test : victoire immédiate Moins 21
    // -------------------------------------------------------------------------

    public function testImmediateVictoryMoinsVingtEtUn(): void
    {
        $user1 = $this->createUser('alice', 'alice@test.com');
        $user2 = $this->createUser('bob', 'bob@test.com');
        $game  = $this->createGame($user1, $user2);

        $game->setStatus(GameStatus::PLAYING);
        $game->setCurrentLeader($user1);

        $players = $this->gamePlayerRepository->findByGame($game);

        // Main de alice : somme = 3+3+4+4+5 = 19 (≤ 21 → victoire immédiate)
        $hand1 = [
            ['value' => 3, 'suit' => 'H'],
            ['value' => 3, 'suit' => 'D'],
            ['value' => 4, 'suit' => 'H'],
            ['value' => 4, 'suit' => 'D'],
            ['value' => 5, 'suit' => 'H'],
        ];
        // Main de bob : somme = 6+6+7+7+8 = 34
        $hand2 = [
            ['value' => 6, 'suit' => 'H'],
            ['value' => 6, 'suit' => 'D'],
            ['value' => 7, 'suit' => 'H'],
            ['value' => 7, 'suit' => 'D'],
            ['value' => 8, 'suit' => 'H'],
        ];

        $players[0]->setHand($hand1);
        $players[1]->setHand($hand2);
        $this->em->flush();

        $result = $this->gameEngine->checkImmediateVictory($game, $players[0], $players[1]);

        $this->assertNotNull($result);
        $this->assertEquals('finished', $result['status']);
        $this->assertEquals(WinType::MOINS_21, $result['winType']);
        $this->assertEquals($user1->getUsername(), $result['winner']->getUsername());
        $this->assertEquals(GameStatus::FINISHED, $game->getStatus());
        $this->assertSame(10, $user1->getCredits());
        $this->assertSame(-10, $user2->getCredits());
    }

    // -------------------------------------------------------------------------
    // Test : victoire immédiate Three 7
    // -------------------------------------------------------------------------

    public function testImmediateVictoryThreeSeven(): void
    {
        $user1 = $this->createUser('alice', 'alice@test.com');
        $user2 = $this->createUser('bob', 'bob@test.com');
        $game  = $this->createGame($user1, $user2);

        $game->setStatus(GameStatus::PLAYING);
        $game->setCurrentLeader($user1);

        $players = $this->gamePlayerRepository->findByGame($game);

        // Bob a trois 7 → victoire immédiate
        $hand1 = [
            ['value' => 6, 'suit' => 'H'],
            ['value' => 6, 'suit' => 'D'],
            ['value' => 8, 'suit' => 'H'],
            ['value' => 8, 'suit' => 'D'],
            ['value' => 8, 'suit' => 'C'],
        ];
        $hand2 = [
            ['value' => 7, 'suit' => 'H'],
            ['value' => 7, 'suit' => 'D'],
            ['value' => 7, 'suit' => 'C'],
            ['value' => 3, 'suit' => 'H'],
            ['value' => 4, 'suit' => 'S'],
        ];

        $players[0]->setHand($hand1);
        $players[1]->setHand($hand2);
        $this->em->flush();

        $result = $this->gameEngine->checkImmediateVictory($game, $players[0], $players[1]);

        $this->assertNotNull($result);
        $this->assertEquals(WinType::THREE_SEVEN, $result['winType']);
        $this->assertEquals($user2->getUsername(), $result['winner']->getUsername());
        $this->assertSame(-10, $user1->getCredits());
        $this->assertSame(10, $user2->getCredits());
    }

    // -------------------------------------------------------------------------
    // Test : partie complète en 5 plis — Match Simple
    // -------------------------------------------------------------------------

    public function testFullGameMatchSimple(): void
    {
        $user1 = $this->createUser('alice', 'alice@test.com');
        $user2 = $this->createUser('bob', 'bob@test.com');
        $game  = $this->createGame($user1, $user2);

        // Alice n'a que des carreaux et des piques
        // Bob n'a que des trèfles et des cœurs
        // Ainsi ils ne peuvent jamais se suivre → le meneur gagne toujours
        $hand1 = [
            ['value' => 6, 'suit' => 'D'],
            ['value' => 7, 'suit' => 'D'],
            ['value' => 8, 'suit' => 'D'],
            ['value' => 6, 'suit' => 'C'],
            ['value' => 7, 'suit' => 'C'],
        ];
        $hand2 = [
            ['value' => 5, 'suit' => 'H'],
            ['value' => 6, 'suit' => 'H'],
            ['value' => 7, 'suit' => 'H'],
            ['value' => 8, 'suit' => 'H'],
            ['value' => 3, 'suit' => 'S'],
        ];

        $game->setStatus(GameStatus::PLAYING);
        $game->setCurrentLeader($user1);
        $players = $this->gamePlayerRepository->findByGame($game);
        $players[0]->setHand($hand1);
        $players[1]->setHand($hand2);
        $this->em->flush();

        $round = new \App\Entity\Round();
        $round->setGame($game);
        $round->setNumber(1);
        $round->setLeader($user1);
        $this->em->persist($round);
        $this->em->flush();

        // Pli 1 : alice 6D, bob 5H (ne suit pas → alice gagne)
        $r = $this->gameEngine->playCard($game, $user1, 6, 'D');
        $this->assertEquals('waiting', $r['status'], $r['error'] ?? '');
        $r = $this->gameEngine->playCard($game, $user2, 5, 'H');
        $this->assertEquals('round_complete', $r['status'], $r['error'] ?? '');
        $this->assertEquals($user1->getUsername(), $r['roundWinner']->getUsername());

        // Pli 2 : alice 7D, bob 6H (ne suit pas → alice gagne)
        $r = $this->gameEngine->playCard($game, $user1, 7, 'D');
        $this->assertEquals('waiting', $r['status'], $r['error'] ?? '');
        $r = $this->gameEngine->playCard($game, $user2, 6, 'H');
        $this->assertEquals('round_complete', $r['status'], $r['error'] ?? '');
        $this->assertEquals($user1->getUsername(), $r['roundWinner']->getUsername());

        // Pli 3 : alice 8D, bob 7H (ne suit pas → alice gagne)
        $r = $this->gameEngine->playCard($game, $user1, 8, 'D');
        $this->assertEquals('waiting', $r['status'], $r['error'] ?? '');
        $r = $this->gameEngine->playCard($game, $user2, 7, 'H');
        $this->assertEquals('round_complete', $r['status'], $r['error'] ?? '');
        $this->assertEquals($user1->getUsername(), $r['roundWinner']->getUsername());

        // Pli 4 : alice 6C, bob 8H (ne suit pas → alice gagne)
        $r = $this->gameEngine->playCard($game, $user1, 6, 'C');
        $this->assertEquals('waiting', $r['status'], $r['error'] ?? '');
        $r = $this->gameEngine->playCard($game, $user2, 8, 'H');
        $this->assertEquals('round_complete', $r['status'], $r['error'] ?? '');

        // Pli 5 (dernier) : alice 7C, bob 3S (ne suit pas → alice gagne → Match Simple)
        $r = $this->gameEngine->playCard($game, $user1, 7, 'C');
        $this->assertEquals('waiting', $r['status'], $r['error'] ?? '');
        $r = $this->gameEngine->playCard($game, $user2, 3, 'S');
        $this->assertEquals('finished', $r['status'], $r['error'] ?? '');
        $this->assertEquals(WinType::MATCH_SIMPLE, $r['winType']);
        $this->assertEquals($user1->getUsername(), $r['winner']->getUsername());
        $this->assertEquals(GameStatus::FINISHED, $game->getStatus());
        $this->assertSame(10, $user1->getCredits());
        $this->assertSame(-10, $user2->getCredits());
    }

    // -------------------------------------------------------------------------
    // Test : Korat
    // -------------------------------------------------------------------------

    public function testKorat(): void
    {
        $user1 = $this->createUser('alice', 'alice@test.com');
        $user2 = $this->createUser('bob', 'bob@test.com');
        $game  = $this->createGame($user1, $user2);

        // Même principe : couleurs séparées, meneur gagne toujours
        // Au pli 5, alice joue un 3 → Korat
        $hand1 = [
            ['value' => 6, 'suit' => 'D'],
            ['value' => 7, 'suit' => 'D'],
            ['value' => 8, 'suit' => 'D'],
            ['value' => 6, 'suit' => 'C'],
            ['value' => 3, 'suit' => 'C'], // alice joue ce 3 au pli 5
        ];
        $hand2 = [
            ['value' => 5, 'suit' => 'H'],
            ['value' => 6, 'suit' => 'H'],
            ['value' => 7, 'suit' => 'H'],
            ['value' => 8, 'suit' => 'H'],
            ['value' => 4, 'suit' => 'S'],
        ];

        $game->setStatus(GameStatus::PLAYING);
        $game->setCurrentLeader($user1);
        $players = $this->gamePlayerRepository->findByGame($game);
        $players[0]->setHand($hand1);
        $players[1]->setHand($hand2);
        $this->em->flush();

        $round = new \App\Entity\Round();
        $round->setGame($game);
        $round->setNumber(1);
        $round->setLeader($user1);
        $this->em->persist($round);
        $this->em->flush();

        // Pli 1 : alice 6D, bob 5H (ne suit pas → alice gagne)
        $r = $this->gameEngine->playCard($game, $user1, 6, 'D');
        $this->assertEquals('waiting', $r['status'], $r['error'] ?? '');
        $r = $this->gameEngine->playCard($game, $user2, 5, 'H');
        $this->assertEquals('round_complete', $r['status'], $r['error'] ?? '');

        // Pli 2 : alice 7D, bob 6H (ne suit pas → alice gagne)
        $r = $this->gameEngine->playCard($game, $user1, 7, 'D');
        $this->assertEquals('waiting', $r['status'], $r['error'] ?? '');
        $r = $this->gameEngine->playCard($game, $user2, 6, 'H');
        $this->assertEquals('round_complete', $r['status'], $r['error'] ?? '');

        // Pli 3 : alice 8D, bob 7H (ne suit pas → alice gagne)
        $r = $this->gameEngine->playCard($game, $user1, 8, 'D');
        $this->assertEquals('waiting', $r['status'], $r['error'] ?? '');
        $r = $this->gameEngine->playCard($game, $user2, 7, 'H');
        $this->assertEquals('round_complete', $r['status'], $r['error'] ?? '');

        // Pli 4 : alice 6C, bob 8H (ne suit pas → alice gagne)
        $r = $this->gameEngine->playCard($game, $user1, 6, 'C');
        $this->assertEquals('waiting', $r['status'], $r['error'] ?? '');
        $r = $this->gameEngine->playCard($game, $user2, 8, 'H');
        $this->assertEquals('round_complete', $r['status'], $r['error'] ?? '');

        // Pli 5 : alice joue 3C (Korat potentiel), bob 4S (ne suit pas → alice gagne → Korat!)
        $r = $this->gameEngine->playCard($game, $user1, 3, 'C');
        $this->assertEquals('waiting', $r['status'], $r['error'] ?? '');
        $r = $this->gameEngine->playCard($game, $user2, 4, 'S');
        $this->assertEquals('finished', $r['status'], $r['error'] ?? '');
        $this->assertEquals(WinType::KORAT, $r['winType']);
        $this->assertEquals($user1->getUsername(), $r['winner']->getUsername());

        // Recharger le game depuis la DB pour avoir le result
        $this->em->refresh($game);
        $this->em->refresh($user1);
        $this->em->refresh($user2);
        $this->assertEquals(2, $game->getResult()->getStakeMultiplier());
        $this->assertSame(20, $user1->getCredits());
        $this->assertSame(-20, $user2->getCredits());
    }
    // -------------------------------------------------------------------------
    // Test : obligation de suivre la couleur
    // -------------------------------------------------------------------------

    public function testMustFollowSuit(): void
    {
        $user1 = $this->createUser('alice', 'alice@test.com');
        $user2 = $this->createUser('bob', 'bob@test.com');
        $game  = $this->createGame($user1, $user2);

        $hand1 = [
            ['value' => 6, 'suit' => 'H'],
            ['value' => 7, 'suit' => 'H'],
            ['value' => 8, 'suit' => 'H'],
            ['value' => 6, 'suit' => 'D'],
            ['value' => 7, 'suit' => 'D'],
        ];
        $hand2 = [
            ['value' => 5, 'suit' => 'H'], // bob a un cœur
            ['value' => 6, 'suit' => 'C'],
            ['value' => 7, 'suit' => 'C'],
            ['value' => 8, 'suit' => 'C'],
            ['value' => 8, 'suit' => 'D'],
        ];

        $game->setStatus(GameStatus::PLAYING);
        $game->setCurrentLeader($user1);
        $players = $this->gamePlayerRepository->findByGame($game);
        $players[0]->setHand($hand1);
        $players[1]->setHand($hand2);
        $this->em->flush();

        $round = new \App\Entity\Round();
        $round->setGame($game);
        $round->setNumber(1);
        $round->setLeader($user1);
        $this->em->persist($round);
        $this->em->flush();

        // Alice joue un cœur
        $r = $this->gameEngine->playCard($game, $user1, 6, 'H');
        $this->assertEquals('waiting', $r['status']);

        // Bob essaie de jouer trèfle alors qu'il a un cœur → erreur
        $r = $this->gameEngine->playCard($game, $user2, 6, 'C');
        $this->assertEquals('error', $r['status']);
        $this->assertStringContainsString('H', $r['error']);

        // Bob joue correctement son cœur
        $r = $this->gameEngine->playCard($game, $user2, 5, 'H');
        $this->assertEquals('round_complete', $r['status']);
    }

    // -------------------------------------------------------------------------
    // Test : dernière carte — pas d'obligation de suivre
    // -------------------------------------------------------------------------

    public function testLastCardNoMustFollow(): void
    {
        $user1 = $this->createUser('alice', 'alice@test.com');
        $user2 = $this->createUser('bob', 'bob@test.com');
        $game  = $this->createGame($user1, $user2);

        // Couleurs complètement séparées → meneur gagne toujours
        $hand1 = [
            ['value' => 6, 'suit' => 'D'],
            ['value' => 7, 'suit' => 'D'],
            ['value' => 8, 'suit' => 'D'],
            ['value' => 6, 'suit' => 'C'],
            ['value' => 7, 'suit' => 'C'],
        ];
        $hand2 = [
            ['value' => 5, 'suit' => 'H'],
            ['value' => 6, 'suit' => 'H'],
            ['value' => 7, 'suit' => 'H'],
            ['value' => 8, 'suit' => 'H'],
            ['value' => 4, 'suit' => 'S'], // dernière carte de bob au pli 5
        ];

        $game->setStatus(GameStatus::PLAYING);
        $game->setCurrentLeader($user1);
        $players = $this->gamePlayerRepository->findByGame($game);
        $players[0]->setHand($hand1);
        $players[1]->setHand($hand2);
        $this->em->flush();

        $round = new \App\Entity\Round();
        $round->setGame($game);
        $round->setNumber(1);
        $round->setLeader($user1);
        $this->em->persist($round);
        $this->em->flush();

        // Plis 1-4 : alice gagne tout (couleurs séparées)
        $this->gameEngine->playCard($game, $user1, 6, 'D');
        $this->gameEngine->playCard($game, $user2, 5, 'H');

        $this->gameEngine->playCard($game, $user1, 7, 'D');
        $this->gameEngine->playCard($game, $user2, 6, 'H');

        $this->gameEngine->playCard($game, $user1, 8, 'D');
        $this->gameEngine->playCard($game, $user2, 7, 'H');

        $this->gameEngine->playCard($game, $user1, 6, 'C');
        $this->gameEngine->playCard($game, $user2, 8, 'H');

        // Pli 5 : alice meneur joue 7C (demande trèfle)
        $r = $this->gameEngine->playCard($game, $user1, 7, 'C');
        $this->assertEquals('waiting', $r['status'], $r['error'] ?? '');

        // Bob n'a que 4S (pique) — pas de trèfle
        // C'est sa dernière carte → exception : pas d'obligation de suivre
        $r = $this->gameEngine->playCard($game, $user2, 4, 'S');
        $this->assertNotEquals('error', $r['status'],
            'Bob devrait pouvoir jouer sa dernière carte sans suivre : ' . ($r['error'] ?? '')
        );
        $this->assertEquals('finished', $r['status']);
    }
    public function testDebugPlayCard(): void
    {
        $user1 = $this->createUser('alice', 'alice@test.com');
        $user2 = $this->createUser('bob', 'bob@test.com');
        $game = $this->createGame($user1, $user2);

        $hand1 = [
            ['value' => 6, 'suit' => 'H'],
            ['value' => 7, 'suit' => 'H'],
            ['value' => 8, 'suit' => 'H'],
            ['value' => 6, 'suit' => 'D'],
            ['value' => 7, 'suit' => 'D'],
        ];
        $hand2 = [
            ['value' => 5, 'suit' => 'H'],
            ['value' => 6, 'suit' => 'C'],
            ['value' => 7, 'suit' => 'C'],
            ['value' => 8, 'suit' => 'C'],
            ['value' => 8, 'suit' => 'D'],
        ];

        $game->setStatus(GameStatus::PLAYING);
        $game->setCurrentLeader($user1);
        $players = $this->gamePlayerRepository->findByGame($game);
        $players[0]->setHand($hand1);
        $players[1]->setHand($hand2);
        $this->em->flush();

        $round = new \App\Entity\Round();
        $round->setGame($game);
        $round->setNumber(1);
        $round->setLeader($user1);
        $this->em->persist($round);
        $this->em->flush();

        // Pli 1 - meneur alice joue 6H
        $r1 = $this->gameEngine->playCard($game, $user1, 6, 'H');
        $this->assertEquals('waiting', $r1['status'], 'Pli1 user1: ' . ($r1['error'] ?? ''));

        // Bob DOIT suivre cœur (il a 5H) → joue 5H
        $r2 = $this->gameEngine->playCard($game, $user2, 5, 'H');
        $this->assertEquals('round_complete', $r2['status'], 'Pli1 user2: ' . ($r2['error'] ?? ''));

        $this->assertEquals(2, $game->getCurrentRound());
        $this->assertEquals($user1->getId(), $game->getCurrentLeader()?->getId());

        // Pli 2 - alice joue 7H
        $r3 = $this->gameEngine->playCard($game, $user1, 7, 'H');
        $this->assertEquals('waiting', $r3['status'], 'Pli2 user1: ' . ($r3['error'] ?? ''));
    }
}
