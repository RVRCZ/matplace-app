<?php

namespace App\Support;

use JsonSerializable;

/**
 * An amount with its currency. Never changes: every operation gives a new one.
 *
 * Prices are defined in crowns. An amount shown or charged in euros comes from the fixed rate in config/farm.php
 * (`eur_rate`, crowns per euro) and is rounded up to ten cents, so the euro price never undercuts the crown one.
 */
final class Money implements JsonSerializable
{
    public const CZK = 'CZK';

    public const EUR = 'EUR';

    public const CURRENCIES = [self::CZK, self::EUR];

    private const SYMBOLS = [self::CZK => 'Kč', self::EUR => '€'];

    public readonly float $amount;

    public readonly string $currency;

    public function __construct(float $amount, string $currency = self::CZK)
    {
        $currency = strtoupper($currency);
        if (! in_array($currency, self::CURRENCIES, true)) {
            throw new \InvalidArgumentException("Unknown currency '{$currency}'.");
        }
        $this->amount = round($amount, 2);
        $this->currency = $currency;
    }

    public static function czk(float $amount): self
    {
        return new self($amount, self::CZK);
    }

    public static function eur(float $amount): self
    {
        return new self($amount, self::EUR);
    }

    public static function of(float $amount, ?string $currency): self
    {
        return new self($amount, $currency ?: self::CZK);
    }

    /** A price defined in crowns, in the currency this visitor sees prices in. */
    public static function price(float $czk, ?string $currency = null): self
    {
        return self::czk($czk)->to($currency ?? Currency::current());
    }

    /** Crowns per euro. */
    public static function rate(): float
    {
        return max(1.0, (float) config('farm.eur_rate', 25));
    }

    public static function symbol(string $currency): string
    {
        return self::SYMBOLS[strtoupper($currency)] ?? strtoupper($currency);
    }

    /**
     * Crowns → euros: rounded up to 0.10 €. Euros → crowns: rounded up to a whole crown. The sign is kept and the
     * rounding works on the size, so a refund is the mirror of the charge.
     */
    public function to(string $currency): self
    {
        $currency = strtoupper($currency);
        if ($currency === $this->currency) {
            return $this;
        }
        $size = abs($this->amount);
        $sign = $this->amount < 0 ? -1 : 1;
        $converted = $currency === self::EUR
            ? ceil(round($size / self::rate() * 10, 6)) / 10
            : ceil(round($size * self::rate(), 6));

        return new self($sign * $converted, $currency);
    }

    /** The same value at the plain rate, to the cent: for breakdown lines and for what a designer is credited. */
    public function exactly(string $currency): self
    {
        $currency = strtoupper($currency);
        if ($currency === $this->currency) {
            return $this;
        }

        return new self($currency === self::EUR ? $this->amount / self::rate() : $this->amount * self::rate(), $currency);
    }

    public function plus(self $other): self
    {
        return new self($this->amount + $this->same($other)->amount, $this->currency);
    }

    public function minus(self $other): self
    {
        return new self($this->amount - $this->same($other)->amount, $this->currency);
    }

    public function times(float $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    public function isZero(): bool
    {
        return abs($this->amount) < 0.005;
    }

    public function isPositive(): bool
    {
        return $this->amount >= 0.005;
    }

    /** Enough to pay $price (a hair of tolerance for sums of cents). */
    public function covers(self $price): bool
    {
        return $this->amount + 1e-6 >= $this->same($price)->amount;
    }

    /**
     * "1 250 Kč", "49,90 €" (cs), "1,250 Kč", "€49.90" (en), "1.250 Kč", "49,90 €" (es). Crowns without decimals
     * when there are none; euros always with cents. Spaces inside are non-breaking: a price never wraps.
     */
    public function format(?string $locale = null, bool $signed = false): string
    {
        $locale = in_array($locale ??= app()->getLocale(), ['cs', 'en', 'es'], true) ? $locale : 'en';
        $size = abs($this->amount);
        $decimals = $this->currency === self::EUR || abs($size - round($size)) >= 0.005 ? 2 : 0;
        [$point, $thousands] = match ($locale) {
            'cs' => [',', "\u{00A0}"],
            'es' => [',', '.'],
            default => ['.', ','],
        };
        $number = number_format($size, $decimals, $point, $thousands);
        $sign = $this->amount < -0.004 ? "\u{2212}" : ($signed && $this->amount > 0.004 ? '+' : '');
        $symbol = self::symbol($this->currency);

        return $sign.($locale === 'en' && $this->currency === self::EUR ? $symbol.$number : $number."\u{00A0}".$symbol);
    }

    /** Just the number, as the locale writes it (for a big figure with the currency set beside it). */
    public function number(?string $locale = null): string
    {
        return trim(str_replace(self::symbol($this->currency), '', $this->format($locale)), " \u{00A0}");
    }

    public function __toString(): string
    {
        return $this->format();
    }

    /** @return array{amount: float, currency: string, text: string} */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency, 'text' => $this->format()];
    }

    /** What a Blade template hands to @money: a Money as it is, a bare number as a price defined in crowns. */
    public static function show(self|float|int|string|null $value, ?string $currency = null): string
    {
        if ($value instanceof self) {
            return $value->format();
        }

        return $currency !== null ? self::of((float) $value, $currency)->format() : self::price((float) $value)->format();
    }

    private function same(self $other): self
    {
        if ($other->currency !== $this->currency) {
            throw new CurrencyMismatch($this->currency, $other->currency);
        }

        return $other;
    }
}
