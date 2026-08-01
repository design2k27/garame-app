<?php
// tests/Unit/CardDeckServiceTest.php

namespace App\Tests\Unit;

use App\Service\CardDeckService;
use PHPUnit\Framework\TestCase;

class CardDeckServiceTest extends TestCase
{
    private CardDeckService $service;

    protected function setUp(): void
    {
        $this->service = new CardDeckService();
    }

    public function testDeckHas23Cards(): void
    {
        $deck = $this->service->generateShuffledDeck();
        $this->assertCount(23, $deck);
    }

    public function testEightOfSpadeIsRemoved(): void
    {
        $deck = $this->service->generateShuffledDeck();
        foreach ($deck as $card) {
            $this->assertFalse(
                $card['value'] === 8 && $card['suit'] === 'S',
                'Le 8 de pique ne doit pas être dans le paquet.'
            );
        }
    }

    public function testDealGivesFiveCardsEach(): void
    {
        [$hand1, $hand2] = $this->service->deal();
        $this->assertCount(5, $hand1);
        $this->assertCount(5, $hand2);
    }

    public function testDealHandsAreSortedByValue(): void
    {
        [$hand1, $hand2] = $this->service->deal();
        for ($i = 0; $i < count($hand1) - 1; $i++) {
            $this->assertLessThanOrEqual($hand1[$i + 1]['value'], $hand1[$i]['value']);
        }
    }

    public function testMoinsVingtEtUn(): void
    {
        $hand = [
            ['value' => 3, 'suit' => 'H'],
            ['value' => 3, 'suit' => 'D'],
            ['value' => 3, 'suit' => 'C'],
            ['value' => 4, 'suit' => 'H'],
            ['value' => 8, 'suit' => 'H'],
        ]; // somme = 21
        $this->assertTrue($this->service->isMoinsVingtEtUn($hand));

        $hand[4]['value'] = 8; // somme = 21 → toujours vrai
        $this->assertTrue($this->service->isMoinsVingtEtUn($hand));

        $hand[0]['value'] = 8; // somme = 26 → faux
        $this->assertFalse($this->service->isMoinsVingtEtUn($hand));
    }

    public function testThreeSeven(): void
    {
        $hand = [
            ['value' => 7, 'suit' => 'H'],
            ['value' => 7, 'suit' => 'D'],
            ['value' => 7, 'suit' => 'C'],
            ['value' => 3, 'suit' => 'H'],
            ['value' => 4, 'suit' => 'S'],
        ];
        $this->assertTrue($this->service->isThreeSeven($hand));

        $hand[2]['value'] = 6; // plus que 2 sept
        $this->assertFalse($this->service->isThreeSeven($hand));
    }

    public function testIsValidCard(): void
    {
        $this->assertTrue($this->service->isValidCard(7, 'H'));
        $this->assertFalse($this->service->isValidCard(8, 'S')); // retiré
        $this->assertFalse($this->service->isValidCard(2, 'H')); // hors plage
        $this->assertFalse($this->service->isValidCard(7, 'X')); // couleur invalide
    }
}
