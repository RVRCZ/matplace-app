<?php

namespace App\Domain\Designer;

use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use App\Models\User;
use App\Support\Track;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Switching the designer profile on, and what happens to it when the account goes. */
final class DesignerProfiles
{
    public function __construct(private readonly DesignerImages $images) {}

    /** Anybody with a verified e-mail can be a designer; no printer needed. Safe to call twice. */
    public function enable(User $user): DesignerProfile
    {
        return DB::transaction(function () use ($user) {
            $profile = DesignerProfile::firstOrCreate(['user_id' => $user->id], [
                'display_name' => $user->name,
                'slug' => DesignerProfile::makeSlug($user->name),
                'visible' => false,   // until the first visible card, or until the designer says so
            ]);
            $user->setRole(User::ROLE_DESIGNER, true);
            if ($profile->wasRecentlyCreated) {
                Track::event('designer_enabled', $profile);
            }

            return $profile;
        });
    }

    /** A card became visible: the profile goes public with its first one. */
    public function cardShown(DesignerModel $model): void
    {
        if ($model->visible) {
            $model->profile->publishOnce();
        }
    }

    /**
     * The account is being deleted: cards are hidden, their pictures and files go. Orders already made keep
     * their own copy of the print file, so prints in progress are not touched.
     */
    public function eraseFor(User $user): void
    {
        $profile = DesignerProfile::where('user_id', $user->id)->first();
        if (! $profile) {
            return;
        }
        foreach ($profile->models()->get() as $model) {
            $this->images->removeAll($model);
            $model->forceFill(['visible' => false, 'model_file_id' => null, 'file_status' => DesignerModel::FILE_NONE, 'slice_summary' => null, 'description' => null])->save();
        }
        $profile->forceFill([
            'visible' => false, 'display_name' => __('user.delete.name', [], 'cs'), 'bio' => null, 'links' => null, 'avatar_path' => null, 'cover_path' => null,
            'printables_username' => null, 'printables_user_id' => null, 'printables_verified_at' => null, 'printables_token' => null,
            'makerworld_handle' => null, 'makerworld_uid' => null, 'makerworld_verified_at' => null, 'makerworld_token' => null,
            'iban' => null, 'iban_owner' => null, 'ico' => null, 'dic' => null,
        ])->save();
        Storage::disk('public')->deleteDirectory('designers/'.$profile->id);
    }
}
