<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Canonical plant weight in grams with a lb/oz/g decomposition for the form.
 * The conversion factors match the SPA's types.ts so a weight entered as
 * components and read back agrees with what the client would compute.
 */
final class Weight
{
    /** Grams per pound conversion factor. */
    private const GRAMS_PER_POUND = 453.592;

    /** Grams per ounce conversion factor. */
    private const GRAMS_PER_OUNCE = 28.3495;

    /**
     * @param integer $grams
     */
    private function __construct(public readonly int $grams) {}

    /**
     * @param integer $grams
     *
     * @return self
     */
    public static function fromGrams(int $grams): self
    {
        return new self($grams);
    }

    /**
     * @param float $pounds
     * @param float $ounces
     * @param float $grams
     *
     * @return self
     */
    public static function fromComponents(float $pounds, float $ounces, float $grams): self
    {
        $total = $pounds * self::GRAMS_PER_POUND + $ounces * self::GRAMS_PER_OUNCE + $grams;

        return new self((int) round($total));
    }

    /**
     * @return array{lb: int, oz: int, g: float}
     */
    public function toComponents(): array
    {
        $grams = (float) $this->grams;

        $pounds    = (int) floor($grams / self::GRAMS_PER_POUND);
        $remaining = $grams - $pounds * self::GRAMS_PER_POUND;

        $ounces = (int) floor($remaining / self::GRAMS_PER_OUNCE);
        $rest   = round($remaining - $ounces * self::GRAMS_PER_OUNCE, 1);

        return ['lb' => $pounds, 'oz' => $ounces, 'g' => $rest];
    }
}
