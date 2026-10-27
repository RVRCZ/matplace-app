<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The photo of the finished print from the photo box, made presentable (App\Domain\Farm\PrintPhotos, TODO #48):
 * `photo_path` = the web render on the public disk (prints/<token>/photo-web.jpg), `photo_meta` = every render, the
 * backdrop, whether the background was taken off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('timelapse_wide_path');
            $table->json('photo_meta')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'photo_meta']);
        });
    }
};
