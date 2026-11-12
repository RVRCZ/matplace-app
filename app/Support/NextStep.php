<?php

namespace App\Support;

use App\Domain\Farm\FarmGate;
use App\Domain\Farm\FarmState;
use Illuminate\Support\Facades\Lang;

/**
 * What happens after the price. The tool pages promise the next step, so they have to promise the one that exists:
 * an inquiry to printers (marketplace), a print on our farm, or a download for the customer's own printer.
 */
final class NextStep
{
    public const INQUIRY = 'inquiry';

    public const FARM = 'farm';

    public const DOWNLOAD = 'download';

    public const MODES = [self::INQUIRY, self::FARM, self::DOWNLOAD];

    /** The mode a request names instead of the visitor's own (the calculator's cached JSON, ConfigController::calc); null = the visitor's. */
    private static ?string $forced = null;

    public static function force(?string $mode): void
    {
        self::$forced = $mode !== null && in_array($mode, self::MODES, true) ? $mode : null;
    }

    public static function mode(): string
    {
        if (self::$forced !== null) {
            return self::$forced;
        }
        if (config('features.marketplace')) {
            return self::INQUIRY;
        }
        if (FarmGate::offered(auth()->user())) {
            return self::FARM;
        }

        return self::DOWNLOAD;
    }

    /**
     * The text for this mode: "<key>.<mode>" when a variant exists, otherwise the plain key (written for the marketplace).
     *
     * @param  array<string, mixed>  $replace
     */
    public static function text(string $key, array $replace = []): string
    {
        $mode = self::mode();
        // a closed farm (FarmState) names no price of a print: "<key>.closed" where the download wording does
        if ($mode === self::DOWNLOAD && FarmState::isClosed() && Lang::has($key.'.closed')) {
            return __($key.'.closed', $replace);
        }
        $variant = $key.'.'.$mode;

        return Lang::has($variant) ? __($variant, $replace) : __($key, $replace);
    }
}
