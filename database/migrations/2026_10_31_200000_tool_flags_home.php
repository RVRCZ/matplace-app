<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** On the home page or not, as the admin decided at /admin/tools; null = as config/home.php lists it. */
    public function up(): void
    {
        Schema::table('tool_flags', function (Blueprint $table) {
            $table->boolean('home')->nullable()->after('public');
        });
    }

    public function down(): void
    {
        Schema::table('tool_flags', function (Blueprint $table) {
            $table->dropColumn('home');
        });
    }
};
