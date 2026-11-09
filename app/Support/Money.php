<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function minor(string|int $value): int
    {
        $value = str_replace(',', '.', trim((string) $value));
        if (! preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/D', $value, $match)) {
            throw new InvalidArgumentException('Некоректна сума.');
        }

        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    public static function decimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function display(int $minor): string
    {
        return number_format($minor / 100, 2, ',', ' ').' ₴';
    }
}
