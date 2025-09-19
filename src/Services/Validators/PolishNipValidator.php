<?php

declare(strict_types=1);

namespace DjWeb\Payments\Services\Validators;

use DjWeb\Payments\ValueObjects\VatNumber;

final class PolishNipValidator
{
    public function assertPolishNip(VatNumber $number): void
    {
        $weights = [6,5,7,2,3,4,5,6,7];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $number->number[$i]) * $weights[$i];
        }
        $checksum = $sum % 11;
        if ($checksum === 10 || $checksum !== (int) $number->number[9]) {
            throw new \InvalidArgumentException('Invalid Polish NIP checksum');
        }
    }
}
