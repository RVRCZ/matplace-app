<?php

namespace App\Http\Controllers;

use App\Domain\Affiliate\Affiliates;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\OrderFlow;
use App\Models\Partner;
use App\Models\PartnerCommission;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The partner programme as the public and the partner see it (docs/AT.md): the link /r/CODE, the programme's page with
 * the registration, the partner's own page in the account. All of it is 404 (or a plain redirect, for the link) while the
 * programme is switched off in the farm's settings.
 */
class AffiliateController extends Controller
{
    public function __construct(private readonly Affiliates $affiliates, private readonly FarmSettings $settings) {}

    /**
     * /r/{code}?to=/path : counts the click (not a robot's; a visitor once a day), remembers the partner in a cookie for
     * the partner's days and goes to `to` – a path of this site only, never another address – or to the home page.
     * An unknown or switched-off code, or a programme that is off, only redirects.
     */
    public function go(Request $request, string $code): RedirectResponse
    {
        $to = Affiliates::safeLanding($request->query('to'));
        $response = redirect($to);
        if (! $this->affiliates->enabled() || ! preg_match(Partner::CODE_PATTERN, Partner::normalize($code))) {
            return $response;
        }
        $partner = $this->affiliates->click($request, $code);
        if ($partner && \App\Support\Bots::name($request) === null) {
            // HttpOnly (no script reads it), Secure on https (always on the real site), SameSite Lax (a link from elsewhere carries it)
            $response->withCookie(cookie(Affiliates::COOKIE, $partner->code, $partner->cookie_days * 1440, '/', null, $request->isSecure() || app()->environment('production'), true, false, 'lax'));
        }

        return $response;
    }

    /** The programme's page: what it is, the true numbers from the settings, the terms, the registration form. */
    public function program(Request $request): View
    {
        abort_unless($this->affiliates->enabled(), 404);
        $user = $request->user();

        return view('affiliate.program', [
            'percent' => $this->numberText($this->affiliates->defaultPercent()),
            'days' => $this->affiliates->defaultDays(),
            'partner' => $user ? Partner::where('user_id', $user->id)->first() : null,
            'suggested' => $user ? $this->suggestCode($user->name ?: Str::before((string) $user->email, '@')) : '',
            'termsVersion' => (string) $this->settings->get('affiliate_terms_version'),
        ]);
    }

    /** A signed-in user with a verified e-mail registers; one partner per account; active at once with the default numbers. */
    public function register(Request $request, OrderFlow $flow): RedirectResponse
    {
        abort_unless($this->affiliates->enabled(), 404);
        $user = $request->user();
        if (Partner::where('user_id', $user->id)->exists()) {
            return redirect()->route('account.partner');
        }
        $request->merge(['code' => Partner::normalize((string) $request->input('code'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'regex:'.Partner::CODE_PATTERN, 'unique:partners,code'],
            'channels' => ['required', 'string', 'max:300'],
            'payout_details' => ['required', 'string', 'max:1000'],
            'terms' => ['accepted'],
        ], ['code.regex' => __('affiliate.err.code'), 'code.unique' => __('affiliate.err.taken'), 'terms.accepted' => __('affiliate.err.terms')]);

        $partner = Partner::create([
            'user_id' => $user->id, 'name' => $data['name'], 'code' => $data['code'], 'email' => $user->email,
            'commission_percent' => $this->affiliates->defaultPercent(), 'cookie_days' => $this->affiliates->defaultDays(), 'active' => true,
            'channels' => $data['channels'], 'payout_details' => $data['payout_details'],
            'terms_version' => (string) $this->settings->get('affiliate_terms_version'), 'terms_accepted_at' => now(),
        ]);
        try {
            $flow->alertAdmin('Nový partner: '.$partner->name.' ('.$partner->code.')', null, [
                'Jméno: '.$partner->name, 'E-mail: '.$partner->email, 'Kód: '.$partner->code.' · provize '.$this->numberText($partner->commission_percent).' % · odkaz platí '.$partner->cookie_days.' dní',
                'Kde bude odkaz sdílet: '.$partner->channels, 'Zaregistroval se sám a je aktivní; vypnout ho můžete v administraci.',
            ], 'partner', null, route('admin.affiliates'));
        } catch (\Throwable $e) {
            Log::warning('Partner registration mail failed', ['partner' => $partner->id, 'error' => $e->getMessage()]);
        }

        return redirect()->route('account.partner')->with('status', __('affiliate.registered'));
    }

    /** The partner's own page: the link and its builder, clicks by day, orders, commissions with their state, what is payable and paid. */
    public function account(Request $request): View|RedirectResponse
    {
        abort_unless($this->affiliates->enabled(), 404);
        $partner = Partner::where('user_id', $request->user()->id)->first();
        if (! $partner) {
            return redirect()->route('affiliate.program');
        }
        $to = Affiliates::safeLanding($request->query('to'));
        $stats = $this->affiliates->stats($partner);

        return view('affiliate.account', [
            'partner' => $partner,
            'stats' => $stats,
            'link' => $partner->link().($to !== '/' ? '?to='.$to : ''),
            'to' => $to,
            'pages' => ['/' => 'affiliate.page.home', '/tools' => 'affiliate.page.tools', '/farm' => 'affiliate.page.farm'],
            'commissions' => PartnerCommission::with('order:id,number')->where('partner_id', $partner->id)->latest('id')->limit(100)->get(),
            'money' => fn (array $byCurrency): string => $byCurrency ? collect($byCurrency)->map(fn ($v, $c) => Money::of((float) $v, $c)->format())->implode(' + ') : Money::czk(0)->format(),
        ]);
    }

    /** A free code to suggest: the name in A-Z, and digits when it is taken. */
    private function suggestCode(string $name): string
    {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name))) ?: 'PARTNER';
        $base = substr($base, 0, 14);
        $code = $base;
        for ($i = 0; Partner::where('code', $code)->exists() && $i < 50; $i++) {
            $code = $base.random_int(10, 9999);
        }

        return substr($code, 0, 20);
    }

    private function numberText(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, app()->getLocale() === 'en' ? '.' : ',', ''), '0'), ',.');
    }
}
