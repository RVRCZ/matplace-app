<?php

namespace App\Models;

use App\Domain\Calculation\PricingProfile as PricingProfileDto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PrinterProfile extends Model
{
    public const CAPACITY = ['open', 'busy', 'paused'];

    public const LANGUAGES = ['cs', 'sk', 'en', 'de', 'es', 'pl'];

    public const SERVICES = ['express', 'postprocessing', 'design', 'delivery'];

    protected $fillable = [
        'user_id', 'display_name', 'slug', 'company', 'ico', 'logo_path', 'contact_email', 'contact_phone', 'pickup_address',
        'delivery_options', 'lead_time_days', 'capacity', 'next_available_at', 'bio', 'regions', 'visible', 'legacy_printer_id',
        'cover_path', 'video_url', 'video_path', 'languages', 'services', 'ico_verified_at', 'ico_subject_name',
    ];

    protected $casts = [
        'delivery_options' => 'array',
        'regions' => 'array',
        'languages' => 'array',
        'services' => 'array',
        'ico_verified_at' => 'datetime',
        'visible' => 'bool',
        'next_available_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function machines(): HasMany
    {
        return $this->hasMany(PrinterMachine::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(PrinterMaterial::class);
    }

    public function pricingProfiles(): HasMany
    {
        return $this->hasMany(PricingProfile::class);
    }

    public function portfolioItems(): HasMany
    {
        return $this->hasMany(PrinterPortfolioItem::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** Embeddable player address for a YouTube / Vimeo link, null for anything else. */
    public function videoEmbedUrl(): ?string
    {
        $u = (string) $this->video_url;
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $u, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d{6,12})~', $u, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }

    public function mediaUrl(?string $path): ?string
    {
        return $path ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null;
    }

    public function defaultPricing(): ?PricingProfile
    {
        return $this->pricingProfiles->firstWhere('is_default', true) ?? $this->pricingProfiles->first();
    }

    /** Price list as the pure DTO the PriceEngine works with, with the per-material gram price applied. */
    public function pricingDto(?string $materialCode = null, ?PricingProfile $profile = null): ?PricingProfileDto
    {
        $profile ??= $this->defaultPricing();
        if (! $profile) {
            return null;
        }
        $perGram = (float) $profile->price_per_gram;
        if ((float) $profile->hourly_rate <= 0 && $perGram <= 0) {
            return null; // no usable rates yet: never quote 0 Kč
        }
        if ($materialCode) {
            $m = $this->materials->firstWhere('material_code', strtoupper($materialCode));
            if ($m && $m->price_per_gram !== null) {
                $perGram = (float) $m->price_per_gram;
            }
        }

        return new PricingProfileDto(
            key: 'printer:'.$this->id.':'.$profile->id,
            hourlyRate: (float) $profile->hourly_rate,
            pricePerGram: $perGram,
            setupFee: (float) $profile->setup_fee,
            marginPct: (float) $profile->margin_pct,
            minPrice: (float) $profile->min_price,
            leadTimeDays: (int) ($profile->lead_time_days ?: $this->lead_time_days),
            expressPct: (float) $profile->express_pct,
            qtyDiscounts: (array) ($profile->qty_discounts ?? []),
            printerProfileId: $this->id,
            label: $this->display_name,
            timeFactor: (float) ($profile->time_factor ?: 1.0),
        );
    }

    public function offersMaterial(string $code): bool
    {
        return $this->materials->contains(fn (PrinterMaterial $m) => $m->material_code === strtoupper($code) && $m->in_stock);
    }

    /** Fresh, unique slug from a display name. */
    public static function makeSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tiskar';
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    /** Complete enough to be shown to customers (name, at least one material, pricing). */
    public function isReady(): bool
    {
        return $this->visible && $this->materials->isNotEmpty() && $this->defaultPricing() !== null;
    }
}
