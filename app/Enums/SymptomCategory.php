<?php

declare(strict_types=1);

namespace App\Enums;

enum SymptomCategory: string
{
    case Leaf = 'leaf';
    case Stem = 'stem';
    case Root = 'root';
    case Pest = 'pest';
    case Disease = 'disease';
    case General = 'general';
    case Custom = 'custom';
}
