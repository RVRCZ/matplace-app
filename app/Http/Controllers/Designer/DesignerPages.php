<?php

namespace App\Http\Controllers\Designer;

use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use Illuminate\Http\Request;

/** Shared by the designer's own pages: whose profile this is, and that a card really is theirs. */
trait DesignerPages
{
    protected function profileOf(Request $request): DesignerProfile
    {
        return DesignerProfile::where('user_id', $request->user()->id)->firstOrFail();
    }

    protected function cardOf(Request $request, int $card): DesignerModel
    {
        return DesignerModel::where('designer_profile_id', $this->profileOf($request)->id)->findOrFail($card);
    }
}
