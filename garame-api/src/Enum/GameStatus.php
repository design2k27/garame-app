<?php
// src/Enum/GameStatus.php
namespace App\Enum;

enum GameStatus: string
{
    case WAITING  = 'waiting';   // en attente du 2e joueur
    case PLAYING  = 'playing';   // partie en cours
    case FINISHED = 'finished';  // partie terminée
}
