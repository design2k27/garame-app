<?php
// src/Enum/WinType.php
namespace App\Enum;

enum WinType: string
{
    case MOINS_21     = 'moins_21';      // somme ≤ 21 à la distribution
    case THREE_SEVEN  = 'three_seven';   // exactement 3 sept en main
    case MATCH_SIMPLE = 'match_simple';  // gagnant du 5e pli
    case KORAT        = 'korat';         // 3 non battu au 5e pli (mise ×2)
}
