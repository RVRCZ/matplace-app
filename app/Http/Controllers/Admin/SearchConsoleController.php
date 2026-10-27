<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Stats\SearchConsoleClient;
use App\Domain\Stats\SearchConsoleError;
use App\Domain\Stats\SearchConsolePull;
use App\Http\Controllers\Controller;
use App\Models\SearchConsoleAccount;
use App\Models\SearchConsoleRow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * /admin/search-console (docs/AN.md §14): connect the Google account that reads Search Console (OAuth, the callback
 * URI is registered in Google Cloud), pick the property, pull now, and see what the database holds. The numbers
 * themselves are on /admin/stats/tools.
 */
class SearchConsoleController extends Controller
{
    public function __construct(private readonly SearchConsoleClient $client) {}

    public function index(): View
    {
        $account = SearchConsoleAccount::current();
        $days = SearchConsoleRow::query()->whereNull('query')->select('date', DB::raw('SUM(clicks) as clicks'), DB::raw('SUM(impressions) as impressions'))
            ->groupBy('date')->orderByDesc('date')->limit(14)->get();

        return view('admin.search-console.index', [
            'configured' => $this->client->configured(),
            'account' => $account,
            'days' => $days,
            'rows' => SearchConsoleRow::count(),
            'range' => SearchConsoleRow::query()->selectRaw('MIN(date) as first, MAX(date) as last')->first(),
            'callback' => route('admin.search_console.callback'),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        abort_unless($this->client->configured(), 422, 'SEARCH_CONSOLE_CLIENT_ID / YOUTUBE_CLIENT_ID missing in .env');
        $state = Str::random(40);
        $request->session()->put('search_console_oauth_state', $state);

        return redirect()->away($this->client->authUrl(route('admin.search_console.callback'), $state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->session()->pull('search_console_oauth_state');
        if (! $state || ! hash_equals($state, (string) $request->query('state'))) {
            return redirect()->route('admin.search_console.index')->with('error', 'Přihlášení vypršelo, zkuste připojení znovu.');
        }
        if ($request->query('error') || ! $request->query('code')) {
            return redirect()->route('admin.search_console.index')->with('error', 'Google připojení nepovolil: '.$request->query('error', 'bez kódu'));
        }
        try {
            $account = $this->client->connect((string) $request->query('code'), route('admin.search_console.callback'), $request->user()->id);
        } catch (SearchConsoleError $e) {
            return redirect()->route('admin.search_console.index')->with('error', 'Připojení se nepovedlo: '.$e->getMessage());
        }

        return redirect()->route('admin.search_console.index')->with('status', $account->ready()
            ? 'Search Console je připojená, vlastnost '.$account->site_url.'. První stažení: Stáhnout teď, nebo v noci samo (16 měsíců zpět).'
            : 'Účet je připojený; vyberte vlastnost.');
    }

    public function disconnect(): RedirectResponse
    {
        $this->client->disconnect();

        return redirect()->route('admin.search_console.index')->with('status', 'Účet je odpojený. Nová data se stahovat nebudou; stažené řádky zůstávají.');
    }

    /** Which property the rows come from (one of sites.list). */
    public function site(Request $request): RedirectResponse
    {
        $account = SearchConsoleAccount::current();
        abort_unless($account, 404);
        $data = $request->validate(['site' => ['required', 'string', 'max:255']]);
        abort_unless(in_array($data['site'], (array) $account->sites, true), 422, 'Not a property of this account.');
        $account->forceFill(['site_url' => $data['site']])->save();

        return redirect()->route('admin.search_console.index')->with('status', 'Vlastnost '.$data['site'].' je vybraná.');
    }

    /** "Stáhnout teď": the last three days (or the first sixteen months of a fresh account), in this request. */
    public function pull(SearchConsolePull $pull): RedirectResponse
    {
        $account = SearchConsoleAccount::current();
        if (! $account || ! $account->ready()) {
            return redirect()->route('admin.search_console.index')->with('error', 'Nejdřív připojte účet a vyberte vlastnost.');
        }
        $from = $account->last_pulled_at === null ? now()->subMonths(max(1, (int) config('seo.search_console.first_pull_months', 16))) : now()->subDays(3);
        try {
            $result = $pull->pull($from, now());
        } catch (SearchConsoleError $e) {
            return redirect()->route('admin.search_console.index')->with('error', 'Stažení se nepovedlo: '.$e->getMessage().($e->needsReconnect() ? ' Připojte účet znovu.' : ''));
        }

        return redirect()->route('admin.search_console.index')->with('status', sprintf('Staženo %d dní (%s až %s), %d řádků.', $result['days'], $result['from'], $result['to'], $result['rows']));
    }
}
