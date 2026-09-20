<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Public printer page: cover photo, video, languages, services, verified company number. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printer_profiles', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('logo_path');
            $table->string('video_url')->nullable()->after('cover_path');     // YouTube / Vimeo link
            $table->string('video_path')->nullable()->after('video_url');    // imported legacy upload (public disk)
            $table->json('languages')->nullable()->after('bio');             // ["cs","en"]
            $table->json('services')->nullable()->after('languages');        // ["express","postprocessing","design","delivery"]
            $table->timestamp('ico_verified_at')->nullable()->after('ico');
            $table->string('ico_subject_name', 200)->nullable()->after('ico_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('printer_profiles', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'video_url', 'video_path', 'languages', 'services', 'ico_verified_at', 'ico_subject_name']);
        });
    }
};
