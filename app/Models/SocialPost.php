<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One post on the Facebook page or on Instagram about a model or a collection, and what came of publishing it. */
class SocialPost extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['platform', 'subject_type', 'subject_id', 'text', 'link', 'image_url', 'status', 'external_id', 'error', 'created_by', 'posted_at'];

    protected $casts = ['posted_at' => 'datetime'];
}
