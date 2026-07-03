<?php

declare(strict_types=1);

namespace App\Support\Care;

enum DueStatus: string
{
    case Overdue = 'overdue';
    case DueSoon = 'due-soon';
    case Ok = 'ok';

    public static function fromDaysLeft(int $daysLeft): self
    {
        if ($daysLeft < 0) {
            return self::Overdue;
        }

        if ($daysLeft <= 1) {
            return self::DueSoon;
        }

        return self::Ok;
    }
}
