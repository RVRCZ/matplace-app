<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = ['to_user_id', 'from_user_id', 'role_rated', 'score', 'comment', 'status', 'inquiry_id', 'legacy_id', 'created_at'];

    /** Recompute the cached average on the rated user. */
    public static function refreshUser(int $userId): void
    {
        $q = static::where('to_user_id', $userId)->where('status', 'approved');
        User::where('id', $userId)->update([
            'rating_avg' => round((float) $q->avg('score'), 2),
            'rating_count' => $q->count(),
        ]);
    }
}
