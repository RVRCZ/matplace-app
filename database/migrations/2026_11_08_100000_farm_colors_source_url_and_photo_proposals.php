<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A spool bought on the web (TODO #71, docs/AL.md §21): the e-shop page a colour was read from (`source_url`, normalised:
 * no tracking parameters, no fragment, lower-case host; the same link again = another spool of the same colour), what the
 * page said (`source_meta`: host, fetched_at, title, weight, price, the settings as read, the chosen image and its logo
 * verdict, confidence, model) and where the colour's photo came from (`photo_source`: drive | upload | eshop | print; an
 * e-shop photo is never shown to customers). Photos of our own prints wait in farm_color_photo_proposals for the admin's yes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_colors', function (Blueprint $table) {
            $table->string('source_url', 500)->nullable()->after('drive_folder');
            $table->json('source_meta')->nullable()->after('source_url');
            $table->string('photo_source', 16)->nullable()->after('photo_path');
            $table->index('source_url');
        });
        // the photos the catalogue holds today: from the Drive export when the colour has its folder, uploaded by hand otherwise
        DB::table('farm_colors')->whereNotNull('photo_path')->whereNotNull('drive_folder')->update(['photo_source' => 'drive']);
        DB::table('farm_colors')->whereNotNull('photo_path')->whereNull('drive_folder')->update(['photo_source' => 'upload']);

        Schema::create('farm_color_photo_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_color_id')->constrained('farm_colors')->cascadeOnDelete();
            $table->foreignId('farm_order_id')->nullable()->constrained('farm_orders')->nullOnDelete();
            $table->string('path', 255);   // public disk, farm/colors/proposals/…
            $table->string('status', 16)->default('pending');   // pending | approved | rejected
            $table->string('note', 500)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['farm_color_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_color_photo_proposals');
        Schema::table('farm_colors', function (Blueprint $table) {
            $table->dropIndex(['source_url']);
            $table->dropColumn(['source_url', 'source_meta', 'photo_source']);
        });
    }
};
