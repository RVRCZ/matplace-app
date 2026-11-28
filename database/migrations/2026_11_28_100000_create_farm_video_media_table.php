<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The media of a print's videos (/admin/youtube/orders/{order}, docs/AL.md §13.10): one ordered list per order that the
 * Short and the 16:9 are both built from. The design picture, the sliced plate, the growing print and the photographed
 * piece are made by the app (path null, found when the video is built); clips and pictures the admin adds carry a path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_video_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_order_id')->constrained('farm_orders')->cascadeOnDelete();
            $table->string('kind', 16);                 // design, source, sliced, growth, photo, clip, image
            $table->string('path')->nullable();         // farm disk; null = made by the app
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('included')->default(true);
            $table->decimal('seconds', 5, 2)->nullable();   // how long it is shown; null = the default of its kind
            $table->boolean('sound')->default(false);       // a clip keeps its own sound (the music is ducked)
            $table->json('meta')->nullable();               // duration, has_audio, original name, the clip kind it came from
            $table->timestamps();
            $table->index(['farm_order_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_video_media');
    }
};
