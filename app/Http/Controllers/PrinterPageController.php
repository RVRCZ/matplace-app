<?php

namespace App\Http\Controllers;

use App\Models\PrinterProfile;
use App\Models\Rating;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** Public page of one printer: who they are, what they print on, their work and ratings. No prices, no economics. */
class PrinterPageController extends Controller
{
    public function show(PrinterProfile $printerProfile): View
    {
        abort_unless($printerProfile->visible && $printerProfile->user && $printerProfile->user->blocked_at === null, 404);
        $printerProfile->load(['machines', 'materials', 'portfolioItems', 'user']);
        $ratings = Rating::where('to_user_id', $printerProfile->user_id)->where('role_rated', 'printer')->where('status', 'approved')->latest('id')->limit(20)->get();

        return view('printer.public', [
            'p' => $printerProfile,
            'ratings' => $ratings,
            'ratingAvg' => $ratings->count() ? round($ratings->avg('score'), 1) : null,
        ]);
    }

    /** Price lines only know the profile id. */
    public function byId(int $id): RedirectResponse
    {
        $profile = PrinterProfile::findOrFail($id);

        return redirect()->route('printers.show', $profile->slug, 301);
    }
}
