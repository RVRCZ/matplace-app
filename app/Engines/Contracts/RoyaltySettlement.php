<?php

namespace App\Engines\Contracts;

use App\Engines\DTO\SettlementInstruction;
use App\Engines\DTO\SettlementStatus;

/**
 * Settlement of a designer royalty for one file release.
 * v1: manual QR payment printer → designer (files released immediately, status tracked).
 * later: payment gateway (files released after "paid") — swapped via config, nothing else changes.
 */
interface RoyaltySettlement
{
    /** Opens a settlement for a release and returns what the payer has to do. */
    public function open(string $releaseRef, float $amount, string $payeeAccount, string $message): SettlementInstruction;

    public function status(string $releaseRef): SettlementStatus;

    /** Whether files may be handed over while the settlement is still pending. */
    public function releasesOnPending(): bool;

    public function name(): string;
}
