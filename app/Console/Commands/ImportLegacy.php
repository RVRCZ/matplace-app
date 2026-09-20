<?php

namespace App\Console\Commands;

use App\Models\PricingProfile;
use App\Models\PrinterMachine;
use App\Models\PrinterMaterial;
use App\Models\PrinterPortfolioItem;
use App\Models\PrinterProfile;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports accounts from the legacy matplace database (users / printers / designers → one users table with roles).
 * Reads the legacy connection only; idempotent thanks to legacy_* ids. Never writes to the legacy database.
 *
 *   php artisan matplace:import-legacy --dry-run
 *   php artisan matplace:import-legacy
 */
class ImportLegacy extends Command
{
    protected $signature = 'matplace:import-legacy {--dry-run : Only report what would be imported} {--connection=legacy}';

    protected $description = 'Import legacy users, printers and designers into the unified account model';

    private const MATERIAL_MAP = [
        'PLA' => 'PLA', 'PLA+' => 'PLA', 'PETG' => 'PETG', 'ASA' => 'ASA', 'ABS' => 'ASA', 'TPU' => 'TPU', 'TPU / TPE' => 'TPU', 'TPE' => 'TPU',
        'PA' => 'PA', 'PA (NYLON)' => 'PA', 'PA-CF' => 'PA', 'PA6-CF' => 'PA', 'NYLON' => 'PA', 'PC' => 'PA', 'RESIN' => 'RESIN', 'PRYSKYŘICE' => 'RESIN',
    ];

    private array $stats = ['users' => 0, 'printers' => 0, 'designers' => 0, 'ratings' => 0, 'portfolio' => 0, 'linked' => 0, 'skipped' => 0];

    public function handle(): int
    {
        $conn = DB::connection($this->option('connection'));
        $dry = (bool) $this->option('dry-run');

        $this->importUsers($conn, $dry);
        $this->importPrinters($conn, $dry);
        $this->importDesigners($conn, $dry);
        $this->importRatings($conn, $dry);

        $this->table(array_keys($this->stats), [array_values($this->stats)]);
        $this->info($dry ? 'Dry run, nothing written.' : 'Import done.');

        return self::SUCCESS;
    }

    private function importUsers($conn, bool $dry): void
    {
        foreach ($conn->table('users')->orderBy('id')->cursor() as $row) {
            $email = strtolower(trim((string) $row->email));
            if ($email === '') {
                $this->stats['skipped']++;
                continue;
            }
            $user = User::where('legacy_user_id', $row->id)->first() ?? User::where('email', $email)->first();
            $this->stats['users']++;
            if ($dry) {
                continue;
            }
            if (! $user) {
                $user = new User;
                $user->email = $email;
            }
            $user->fill([
                'name' => $row->name ?: explode('@', $email)[0],
                'phone' => $row->phone ?: $user->phone,
                'country' => strlen((string) $row->country) === 2 ? strtoupper($row->country) : 'CZ',
                'zip' => $row->delivery_zip ?? $user->zip,
                'city' => $row->delivery_city ?? $user->city,
                'legacy_user_id' => $row->id,
            ]);
            $this->applyPassword($user, $row->password_hash ?? null, $row->created_at ?? null);
            if (! $user->email_verified_at && ! empty($row->email_verified)) {
                $user->email_verified_at = now();
            }
            $user->notify_email = ($row->notification_pref ?? 'instant') !== 'off';
            $user->created_at = $user->created_at ?? ($row->created_at ?? now());
            $user->save();
            $user->setRole(User::ROLE_CUSTOMER, true);
            $this->linkOauth($user, $row->oauth_provider ?? null, $row->oauth_id ?? null);
        }
    }

    private function importPrinters($conn, bool $dry): void
    {
        foreach ($conn->table('printers')->orderBy('id')->cursor() as $row) {
            $email = strtolower(trim((string) $row->email));
            if ($email === '') {
                $this->stats['skipped']++;
                continue;
            }
            $this->stats['printers']++;
            if ($dry) {
                continue;
            }
            $user = User::where('legacy_printer_id', $row->id)->first()
                ?? (! empty($row->user_id) ? User::where('legacy_user_id', $row->user_id)->first() : null)
                ?? User::where('email', $email)->first();
            if ($user) {
                $this->stats['linked']++;
            } else {
                $user = new User;
                $user->email = $email;
                $user->name = $row->name;
                $user->created_at = $row->created_at ?? now();
            }
            $user->legacy_printer_id = $row->id;
            $user->phone = $user->phone ?: $row->phone;
            $user->city = $user->city ?: $row->city;
            $user->lat = $user->lat ?? ($row->lat ?? null);
            $user->lng = $user->lng ?? ($row->lng ?? null);
            $user->country = strlen((string) ($row->country ?? '')) === 2 ? strtoupper($row->country) : ($user->country ?: 'CZ');
            if (! empty($row->phone_verified) && ! $user->phone_verified_at) {
                $user->phone_verified_at = now();
            }
            $user->email_verified_at = $user->email_verified_at ?? now(); // legacy printers were admin-approved
            if ($row->status === 'suspended') {
                $user->blocked_at = now();
            }
            $this->applyPassword($user, $row->password_hash ?? null, $row->last_login_at ?? null);
            $user->save();
            $user->setRole(User::ROLE_CUSTOMER, true);
            $user->setRole(User::ROLE_PRINTER, $row->status === 'active');
            $this->linkOauth($user, $row->oauth_provider ?? null, $row->oauth_id ?? null);

            $profile = PrinterProfile::firstOrNew(['user_id' => $user->id]);
            $profile->fill([
                'display_name' => $row->name,
                'slug' => $profile->slug ?: (PrinterProfile::where('slug', $row->slug)->exists() ? PrinterProfile::makeSlug($row->name) : $row->slug),
                'company' => $row->company ?: null,
                'ico' => $row->ico ?: null,
                'contact_email' => $email,
                'contact_phone' => $row->phone ?: null,
                'pickup_address' => $row->pickup_address ?: null,
                'lead_time_days' => 5, // legacy only knew the response time, not a delivery time
                'capacity' => empty($row->accepting_orders) ? 'paused' : 'open',
                'next_available_at' => $row->next_available ?? null,
                'bio' => $row->bio ?: null,
                'regions' => self::json($row->regions ?? null),
                'visible' => $row->status === 'active' && ! empty($row->directory_visible ?? 1),
                'legacy_printer_id' => $row->id,
            ]);
            $profile->logo_path = $profile->logo_path; // avatars are copied by a separate file sync
            $profile->save();

            // materials: legacy JSON strings → catalogue codes
            $codes = [];
            foreach (self::json($row->materials ?? null) ?? [] as $m) {
                $code = self::MATERIAL_MAP[strtoupper(trim((string) $m))] ?? null;
                if ($code) {
                    $codes[$code] = true;
                }
            }
            foreach (array_keys($codes) as $code) {
                PrinterMaterial::firstOrCreate(['printer_profile_id' => $profile->id, 'material_code' => $code], ['in_stock' => true]);
            }

            // machines: legacy equipment JSON (names only)
            if ($profile->machines()->count() === 0) {
                foreach (self::json($row->equipment ?? null) ?? [] as $name) {
                    $name = trim((string) $name);
                    if ($name !== '') {
                        PrinterMachine::create(['printer_profile_id' => $profile->id, 'name' => mb_substr($name, 0, 120), 'technology' => 'fdm']);
                    }
                }
            }

            // pricing: legacy per-gram / hourly / min order → default price list
            $pricing = $profile->pricingProfiles()->where('is_default', true)->first() ?? new PricingProfile(['printer_profile_id' => $profile->id, 'name' => 'Standard', 'is_default' => true]);
            if (! $pricing->exists) {
                // legacy stored 0 for "not set": fall back to sensible defaults so nobody quotes 0 Kč
                $pricing->hourly_rate = ((float) ($row->hourly_rate ?? 0)) > 0 ? $row->hourly_rate : 60;
                $pricing->price_per_gram = ((float) ($row->price_per_gram ?? 0)) > 0 ? $row->price_per_gram : 2;
                $pricing->min_price = $row->min_order_price ?? 0;
                $pricing->lead_time_days = $profile->lead_time_days;
                $pricing->save();
            }

            // portfolio photos (paths are copied by the file sync; here only records)
            foreach ($conn->table('printer_portfolio')->where('printer_id', $row->id)->get() as $p) {
                if (PrinterPortfolioItem::where('legacy_id', $p->id)->exists()) {
                    continue;
                }
                PrinterPortfolioItem::create([
                    'printer_profile_id' => $profile->id,
                    'photo_path' => 'legacy/portfolio/'.ltrim((string) $p->image, '/'),
                    'title' => $p->title ?: null,
                    'material_code' => self::MATERIAL_MAP[strtoupper((string) ($p->material ?? ''))] ?? null,
                    'note' => $p->description ?: null,
                    'legacy_id' => $p->id,
                ]);
                $this->stats['portfolio']++;
            }
        }
    }

    private function importDesigners($conn, bool $dry): void
    {
        foreach ($conn->table('designers')->orderBy('id')->cursor() as $row) {
            $email = strtolower(trim((string) $row->email));
            if ($email === '') {
                continue;
            }
            $this->stats['designers']++;
            if ($dry) {
                continue;
            }
            $user = User::where('legacy_designer_id', $row->id)->first() ?? User::where('email', $email)->first();
            if ($user) {
                $this->stats['linked']++;
            } else {
                $user = new User;
                $user->email = $email;
                $user->name = $row->name;
                $user->created_at = $row->created_at ?? now();
            }
            $user->legacy_designer_id = $row->id;
            $this->applyPassword($user, $row->password_hash ?? null, null);
            $user->email_verified_at = $user->email_verified_at ?? (! empty($row->email_verified) ? now() : null);
            $user->save();
            $user->setRole(User::ROLE_CUSTOMER, true);
            $user->setRole(User::ROLE_DESIGNER, $row->status === 'active');
            // designer profile (portfolio, licences) is created in step 5; the role switch is enough for now
        }
    }

    private function importRatings($conn, bool $dry): void
    {
        foreach ($conn->table('printer_ratings')->orderBy('id')->cursor() as $r) {
            $this->stats['ratings']++;
            if ($dry || Rating::where('legacy_id', $r->id)->exists()) {
                continue;
            }
            $to = User::where('legacy_printer_id', $r->printer_id)->first();
            if (! $to) {
                continue;
            }
            $from = ! empty($r->customer_user_id) ? User::where('legacy_user_id', $r->customer_user_id)->first() : null;
            Rating::create([
                'to_user_id' => $to->id,
                'from_user_id' => $from?->id,
                'role_rated' => 'printer',
                'score' => max(1, min(5, (int) $r->rating)),
                'comment' => $r->comment ?: null,
                'status' => ($r->status ?? 'approved') === 'rejected' ? 'rejected' : 'approved',
                'legacy_id' => $r->id,
                'created_at' => $r->created_at ?? now(),
            ]);
        }
        if (! $dry) {
            foreach (Rating::query()->distinct()->pluck('to_user_id') as $uid) {
                Rating::refreshUser((int) $uid);
            }
        }
    }

    /** Keeps the newest usable bcrypt hash; legacy password_hash() output is compatible with Laravel's Hash. */
    private function applyPassword(User $user, ?string $hash, ?string $lastLogin): void
    {
        if (! $hash || ! str_starts_with($hash, '$2')) {
            return;
        }
        if (empty($user->password) || $lastLogin) {
            $user->forceFill(['password' => $hash]);
        }
    }

    private function linkOauth(User $user, ?string $provider, ?string $id): void
    {
        if ($provider && $id) {
            \App\Models\OauthIdentity::firstOrCreate(['provider' => $provider, 'provider_id' => (string) $id], ['user_id' => $user->id]);
        }
    }

    private static function json(?string $s): ?array
    {
        if ($s === null || $s === '') {
            return null;
        }
        $d = json_decode($s, true);

        return is_array($d) ? array_values(array_filter($d, fn ($x) => $x !== null && $x !== '')) : null;
    }
}
