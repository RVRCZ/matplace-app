<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\FarmCommand;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterMaterial;
use App\Models\FarmPrintJob;
use App\Models\FarmVideo;
use App\Models\PaymentRefund;
use Tests\TestCase;

/**
 * Every state a farm row can be in fits the column that holds it. SQLite, which the tests run on, neither enforces
 * nor even records a varchar's width, MariaDB on the server does: `awaiting_choices` (16) was written into
 * farm_order_events.from/to (varchar 14) and the first big orders died with a 500 (25 Oct 2026). So the width is
 * read from the migrations themselves: the last `string('column', n)` written for the table wins.
 */
final class FarmStatusColumnsTest extends TestCase
{
    /** @return list<string> the string constants of a class whose names start with the prefix */
    private static function constants(string $class, string $prefix): array
    {
        return array_values(array_filter((new \ReflectionClass($class))->getConstants(), fn ($v, $k) => str_starts_with($k, $prefix) && is_string($v), ARRAY_FILTER_USE_BOTH));
    }

    /**
     * The declared widths of the string columns, table → column → width, as the migrations define them in order
     * (a later `change()` replaces an earlier definition); a `string('col')` without a width is 255.
     *
     * @return array<string, array<string, int>>
     */
    private static function widths(): array
    {
        $out = [];
        $files = glob(database_path('migrations/*.php')) ?: [];
        sort($files);
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            // only what up() does: down() writes the old width back
            $src = substr($src, 0, strpos($src, 'function down(') ?: strlen($src));
            // every Schema::create / Schema::table block, up to the next one: the table it names owns the columns inside
            preg_match_all('/Schema::(?:create|table)\(\s*\'(\w+)\'/', $src, $heads, PREG_OFFSET_CAPTURE);
            foreach ($heads[1] as $i => [$table, $at]) {
                $end = $heads[1][$i + 1][1] ?? strlen($src);
                $block = substr($src, $at, $end - $at);
                preg_match_all('/->string\(\s*\'(\w+)\'\s*(?:,\s*(\d+))?\s*\)/', $block, $cols, PREG_SET_ORDER);
                foreach ($cols as $c) {
                    $out[$table][$c[1]] = isset($c[2]) && $c[2] !== '' ? (int) $c[2] : 255;
                }
            }
        }

        return $out;
    }

    public function test_every_state_fits_its_column_as_the_migrations_declare_it(): void
    {
        $widths = self::widths();
        $this->assertSame(20, $widths['farm_orders']['status'] ?? null, 'the widths are read from the migrations');
        $this->assertSame(20, $widths['farm_order_events']['to'] ?? null);
        $this->assertSame(20, $widths['farm_order_events']['from'] ?? null);

        $orderStates = array_keys(FarmOrder::TRANSITIONS);
        $cases = [
            ['farm_orders', 'status', $orderStates],
            ['farm_order_events', 'from', $orderStates],
            ['farm_order_events', 'to', $orderStates],
            ['farm_print_jobs', 'status', self::constants(FarmPrintJob::class, 'STATUS_')],
            ['farm_printers', 'state', self::constants(FarmPrinter::class, 'STATE_')],
            ['farm_printers', 'mode', self::constants(FarmPrinter::class, 'MODE_')],
            ['farm_videos', 'status', self::constants(FarmVideo::class, 'STATUS_')],
            ['farm_videos', 'kind', self::constants(FarmVideo::class, 'KIND_')],
            ['farm_commands', 'type', self::constants(FarmCommand::class, 'TYPE_')],
            ['farm_commands', 'status', ['pending', 'sent', 'done', 'failed']],
            ['farm_printer_materials', 'status', self::constants(FarmPrinterMaterial::class, 'STATUS_')],
            // money back to the card (docs/AO.md §2)
            ['payment_refunds', 'status', self::constants(PaymentRefund::class, 'STATUS_')],
            ['payment_refunds', 'reason', self::constants(PaymentRefund::class, 'REASON_')],
            ['credit_transactions', 'type', self::constants(CreditTransaction::class, 'TYPE_')],
        ];
        foreach ($cases as [$table, $column, $values]) {
            $this->assertNotEmpty($values, "$table.$column: no states to check");
            $width = $widths[$table][$column] ?? null;
            $this->assertNotNull($width, "$table.$column: no string() definition found in the migrations");
            foreach ($values as $value) {
                $this->assertLessThanOrEqual($width, strlen($value), "'$value' (".strlen($value).") does not fit $table.$column (varchar $width)");
            }
        }
    }
}
