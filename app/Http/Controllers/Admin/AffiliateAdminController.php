<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Affiliate\Affiliates;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerCommission;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The operator's two pages of docs/AT.md: /admin/affiliates (partners, commissions, payouts) and /admin/promo-codes. */
class AffiliateAdminController extends Controller
{
    public function __construct(private readonly Affiliates $affiliates) {}

    public function partners(): View
    {
        $partners = Partner::with('user:id,email')->orderByDesc('active')->orderBy('name')->get();
        $stats = $partners->mapWithKeys(fn (Partner $p) => [$p->id => $this->affiliates->stats($p, 30)]);

        return view('admin.affiliates.index', [
            'partners' => $partners, 'stats' => $stats,
            'commissions' => PartnerCommission::with('order:id,number')->whereIn('partner_id', $partners->pluck('id'))->whereIn('state', [PartnerCommission::STATE_PENDING, PartnerCommission::STATE_PAYABLE])->orderBy('id')->get()->groupBy('partner_id'),
            'enabled' => $this->affiliates->enabled(),
            'defaults' => ['percent' => $this->affiliates->defaultPercent(), 'days' => $this->affiliates->defaultDays()],
            'money' => fn (array $byCurrency): string => $byCurrency ? collect($byCurrency)->map(fn ($v, $c) => Money::of((float) $v, $c)->format())->implode(' + ') : '0',
        ]);
    }

    /** Adds a partner (no {partner}) or changes one: the numbers, on/off, the notes. A change of percent never touches a commission already written. */
    public function savePartner(Request $request, ?Partner $partner = null): RedirectResponse
    {
        $request->merge(['code' => Partner::normalize((string) $request->input('code'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'regex:'.Partner::CODE_PATTERN, Rule::unique('partners', 'code')->ignore($partner?->id)],
            'email' => ['required', 'email:rfc', 'max:190'],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'cookie_days' => ['required', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payout_details' => ['nullable', 'string', 'max:1000'],
            'user_email' => ['nullable', 'email:rfc', 'max:190'],
        ]);
        $data['active'] = $request->boolean('active');
        $userEmail = $data['user_email'] ?? null;
        unset($data['user_email']);
        if ($userEmail !== null && $userEmail !== '') {
            $user = User::where('email', strtolower($userEmail))->first();
            if (! $user) {
                return back()->withInput()->withErrors(['user_email' => 'Účet s tímto e-mailem neexistuje.']);
            }
            if (Partner::where('user_id', $user->id)->where('id', '!=', $partner?->id ?? 0)->exists()) {
                return back()->withInput()->withErrors(['user_email' => 'Tento účet už je partnerem.']);
            }
            $data['user_id'] = $user->id;
        }
        $partner ? $partner->update($data) : Partner::create($data);

        return redirect()->route('admin.affiliates')->with('status', 'Uloženo.');
    }

    /** The operator paid commissions out (outside the system): one by one (`ids`) or all payable of the partner. */
    public function markPaid(Request $request, Partner $partner): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:300'], 'ids' => ['nullable', 'array'], 'ids.*' => ['integer']]);
        $n = $this->affiliates->markPaid($partner, $request->boolean('all') ? null : array_map('intval', (array) ($data['ids'] ?? [])), $data['note']);

        return redirect()->route('admin.affiliates')->with('status', $n.' provizí označeno jako vyplacené.');
    }

    public function codes(): View
    {
        return view('admin.promo.index', ['codes' => PromoCode::with('partner:id,name,code')->orderByDesc('active')->latest('id')->get(), 'partners' => Partner::orderBy('name')->get(['id', 'name', 'code']), 'enabled' => app(\App\Domain\Affiliate\Coupons::class)->enabled()]);
    }

    public function saveCode(Request $request, ?PromoCode $code = null): RedirectResponse
    {
        $request->merge(['code' => PromoCode::normalize((string) $request->input('code'))]);
        $data = $request->validate([
            'code' => ['required', 'regex:'.PromoCode::CODE_PATTERN, Rule::unique('promo_codes', 'code')->ignore($code?->id)],
            'kind' => ['required', Rule::in([PromoCode::KIND_PERCENT, PromoCode::KIND_FIXED])],
            'value' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'min_order_czk' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        if ($data['kind'] === PromoCode::KIND_PERCENT && (float) $data['value'] > 100) {
            return back()->withInput()->withErrors(['value' => 'Procento je nejvýš 100.']);
        }
        $data['min_order_czk'] = (float) ($data['min_order_czk'] ?? 0);
        $data['valid_until'] = ! empty($data['valid_until']) ? \Carbon\Carbon::parse($data['valid_until'])->endOfDay() : null;   // "until" is the whole day
        $data['active'] = $request->boolean('active');
        $data['once_per_account'] = $request->boolean('once_per_account');
        $code ? $code->update($data) : PromoCode::create($data);

        return redirect()->route('admin.promo-codes')->with('status', 'Uloženo.');
    }
}
