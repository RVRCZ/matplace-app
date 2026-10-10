<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin's switch of a tool (/admin/tools): `public` = false takes a tool of config/tools.php out of the catalogue,
 * the sitemap and the links, and its page answers 404 to everybody but an admin. A tool without a row is what the
 * config says (App\Domain\Tools\ToolVisibility).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_flags', function (Blueprint $table) {
            $table->id();
            $table->string('tool', 40)->unique();      // the key in config/tools.php
            $table->boolean('public')->default(true);
            $table->text('note')->nullable();          // why it is off, what is left to tune
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_flags');
    }
};
