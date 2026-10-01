<?php

namespace App\Http\Controllers\Designer;

use App\Domain\Designer\OwnershipCheck;
use App\Engines\Import\Sources;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Proving that a Printables / MakerWorld account belongs to the designer: a token put where only its owner can write. */
class VerificationController extends Controller
{
    use DesignerPages;

    public function show(Request $request, string $source, Sources $sources): View
    {
        abort_unless($sources->has($source), 404);
        $profile = $this->profileOf($request);

        return view('designer.verify', [
            'profile' => $profile,
            'source' => $source,
            'token' => $profile->tokenFor($source),
            'verified' => $profile->verifiedOn($source),
            'handle' => $source === 'printables' ? $profile->printables_username : $profile->makerworld_handle,
        ]);
    }

    public function check(Request $request, string $source, Sources $sources, OwnershipCheck $check): RedirectResponse
    {
        abort_unless($sources->has($source), 404);
        $data = $request->validate(['url' => ['required', 'url:http,https', 'max:400']]);
        $result = $check->verify($this->profileOf($request), $source, $data['url']);
        if (! $result['ok']) {
            return back()->withInput()->with('error', __('designer.verify.error.'.$result['error']));
        }

        return redirect()->route('designer.import', $source)->with('status', __('designer.verify.done', ['source' => __('designer.source.'.$source)]));
    }
}
