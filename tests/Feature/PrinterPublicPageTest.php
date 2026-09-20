<?php

namespace Tests\Feature;

use App\Models\PricingProfile;
use App\Models\PrinterMaterial;
use App\Models\PrinterProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Public printer page + the profile extras behind it (cover, video, languages, services, portfolio, verified company number, postcode → position). */
class PrinterPublicPageTest extends TestCase
{
    use RefreshDatabase;

    private function printer(): User
    {
        $user = User::factory()->create(['city' => 'Brno']);
        $user->setRole(User::ROLE_PRINTER, true);
        $profile = PrinterProfile::create(['user_id' => $user->id, 'display_name' => 'Dílna U Draka', 'slug' => 'dilna-u-draka', 'visible' => true, 'bio' => 'Tiskneme od roku 2018.']);
        PricingProfile::create(['printer_profile_id' => $profile->id, 'name' => 'Standard', 'is_default' => true, 'hourly_rate' => 100, 'price_per_gram' => 5, 'lead_time_days' => 3]);
        PrinterMaterial::create(['printer_profile_id' => $profile->id, 'material_code' => 'PLA']);

        return $user->fresh();
    }

    private function form(array $over = []): array
    {
        return $over + [
            'hourly_rate' => 100, 'price_per_gram' => 5, 'lead_time_days' => 3, 'display_name' => 'Dílna U Draka', 'capacity' => 'open',
            'materials' => ['PLA'], 'visible' => 1,
        ];
    }

    public function test_public_page_shows_profile_without_prices_and_hides_invisible_ones(): void
    {
        $user = $this->printer();
        $this->get('/printers/dilna-u-draka')->assertOk()->assertSee('Dílna U Draka')->assertSee('Brno')->assertSee('Tiskneme od roku 2018.')->assertDontSee('Kč/h');
        $this->get('/printers/dilna-u-draka?lang=es')->assertOk();
        $this->get('/printers/id/'.$user->printerProfile->id)->assertRedirect('/printers/dilna-u-draka');

        $user->printerProfile->update(['visible' => false]);
        $this->get('/printers/dilna-u-draka')->assertNotFound();
    }

    public function test_profile_saves_media_languages_services_and_verifies_company_number(): void
    {
        Storage::fake('public');
        Http::fake([
            'ares.gov.cz/*' => Http::response(['ico' => '27074358', 'obchodniJmeno' => 'Asseco Central Europe, a.s.']),
            'nominatim.openstreetmap.org/*' => Http::response([['lat' => '49.19', 'lon' => '16.61']]),
        ]);
        $user = $this->printer();

        $this->actingAs($user)->post(route('printer.profile.update'), $this->form([
            'ico' => '27074358', 'video_url' => 'https://www.youtube.com/watch?v=65TUAl3SB70', 'languages' => ['cs', 'en'], 'services' => ['express', 'design'],
            'zip' => '60200', 'city' => 'Brno',
            'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 400),
            'portfolio' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $p = $user->printerProfile->fresh();
        $this->assertNotNull($p->cover_path);
        Storage::disk('public')->assertExists($p->cover_path);
        $this->assertSame(['cs', 'en'], $p->languages);
        $this->assertSame(['express', 'design'], $p->services);
        $this->assertSame('https://www.youtube-nocookie.com/embed/65TUAl3SB70', $p->videoEmbedUrl());
        $this->assertNotNull($p->ico_verified_at);
        $this->assertSame('Asseco Central Europe, a.s.', $p->ico_subject_name);
        $this->assertSame(2, $p->portfolioItems()->count());
        $this->assertEqualsWithDelta(49.19, (float) $user->fresh()->lat, 0.001);   // postcode → position for distance

        $this->get('/printers/dilna-u-draka')->assertOk()->assertSee('youtube-nocookie.com/embed/65TUAl3SB70', false)->assertSee(__('printer.public.verified'));

        // delete one portfolio photo; a link that is not a video site is refused
        $first = $p->portfolioItems()->first();
        $this->actingAs($user)->post(route('printer.profile.update'), $this->form(['ico' => '27074358', 'portfolio_delete' => [$first->id]]))->assertSessionHasNoErrors();
        $this->assertSame(1, $p->portfolioItems()->count());
        Storage::disk('public')->assertMissing($first->photo_path);
        $this->actingAs($user)->post(route('printer.profile.update'), $this->form(['video_url' => 'https://example.com/x.mp4']))->assertSessionHasErrors('video_url');
    }
}
