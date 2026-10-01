<?php

namespace App\Http\Controllers;

use App\Domain\Account\ModelLibrary;
use App\Domain\Farm\Wallet;
use App\Models\Calculation;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Support\Countries;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** /account: the customer's place. Prints first, then models, then calculations; the profile is one click away. */
class AccountController extends Controller
{
    private const DASH_ORDERS = 5;

    private const DASH_MODELS = 8;

    private const DASH_CALCULATIONS = 10;

    private const PER_PAGE = 24;

    public function index(Request $request, ModelLibrary $library, Wallet $wallet): View
    {
        $user = $request->user()->load('roles');
        $farm = (bool) config('farm.enabled');
        $models = $library->query($user)->limit(self::DASH_MODELS)->get();

        return view('account.index', [
            'user' => $user,
            'farm' => $farm,
            'balance' => $farm ? $wallet->balance($user) : null,
            'orders' => $farm ? FarmOrder::with(['modelFile', 'color'])->where('user_id', $user->id)->where('kind', FarmOrder::KIND_PRINT)->latest('id')->limit(self::DASH_ORDERS)->get() : collect(),
            'ordersTotal' => $farm ? FarmOrder::where('user_id', $user->id)->where('kind', FarmOrder::KIND_PRINT)->count() : 0,
            'models' => $models,
            'modelsTotal' => $library->query($user)->count(),
            'locks' => $library->locks($models),
            'calculations' => Calculation::with('modelFile')->where('owner_user_id', $user->id)->latest('id')->limit(self::DASH_CALCULATIONS)->get(),
            'calculationsTotal' => Calculation::where('owner_user_id', $user->id)->count(),
        ]);
    }

    /** Every model of the account, filtered by where it came from. */
    public function models(Request $request, ModelLibrary $library): View
    {
        $origin = in_array($request->query('origin'), ModelLibrary::ORIGINS, true) ? (string) $request->query('origin') : null;
        $models = $library->query($request->user(), $origin)->paginate(self::PER_PAGE)->withQueryString();

        return view('account.models', ['models' => $models, 'locks' => $library->locks($models->getCollection()), 'origin' => $origin, 'origins' => ModelLibrary::ORIGINS]);
    }

    public function deleteModel(Request $request, ModelFile $modelFile, ModelLibrary $library): RedirectResponse
    {
        abort_unless($modelFile->owner_user_id === $request->user()->id && $modelFile->deleted_at === null, 404);
        if ($lock = $library->lock($modelFile)) {
            return back()->with('error', __('user.models.locked_'.$lock));
        }
        $library->delete($modelFile);

        return back()->with('status', __('user.models.deleted'));
    }

    public function calculations(Request $request): View
    {
        return view('account.calculations', [
            'calculations' => Calculation::with('modelFile')->where('owner_user_id', $request->user()->id)->latest('id')->paginate(self::PER_PAGE),
        ]);
    }

    public function profile(Request $request, Wallet $wallet): View
    {
        $user = $request->user()->load('oauthIdentities');

        return view('account.profile', [
            'user' => $user,
            'countries' => Countries::names(),
            'balance' => $wallet->balance($user),
            'providers' => array_keys(array_filter(['google' => config('services.google.client_id'), 'facebook' => config('services.facebook.client_id')])),
            'packetaKey' => (string) config('services.packeta.api_key'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'delivery_name' => ['nullable', 'string', 'max:120'],
            'street' => ['nullable', 'string', 'max:160'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', Rule::in(Countries::CODES)],
            'locale' => ['nullable', 'in:cs,en,es'],
            'notify_email' => ['nullable', 'boolean'],
            'pickup_point' => ['nullable', 'array'],
            'pickup_point.id' => ['nullable', 'string', 'max:40'],
            'pickup_point.name' => ['nullable', 'string', 'max:200'],
            'pickup_point.carrier_id' => ['nullable', 'string', 'max:40'],
            'pickup_point.country' => ['nullable', 'string', 'size:2'],
        ]);
        $point = $data['pickup_point'] ?? null;
        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'delivery_name' => $data['delivery_name'] ?? null,
            'street' => $data['street'] ?? null,
            'zip' => $data['zip'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => strtoupper($data['country'] ?? $user->country ?? 'CZ'),
            'locale' => $data['locale'] ?? $user->locale,
            'notify_email' => $request->boolean('notify_email'),
            // the widget hands over an id and a name; an empty id means "no favourite point"
            'pickup_point' => ! empty($point['id']) ? [
                'id' => (string) $point['id'], 'name' => (string) ($point['name'] ?? ''),
                'carrier_id' => ($point['carrier_id'] ?? '') !== '' ? (string) $point['carrier_id'] : null,
                'country' => strtoupper((string) ($point['country'] ?? '')) ?: null,
            ] : null,
        ])->save();

        return back()->with('status', __('user.profile.saved'));
    }
}
