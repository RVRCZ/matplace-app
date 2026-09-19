<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One account, roles as switches. Legacy ids make the import idempotent. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();          // OAuth-only accounts
            $table->string('phone', 30)->nullable()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('locale', 5)->default('cs')->after('phone_verified_at');
            $table->string('country', 2)->default('CZ')->after('locale');
            $table->string('zip', 10)->nullable()->after('country');
            $table->string('city', 100)->nullable()->after('zip');
            $table->decimal('lat', 10, 7)->nullable()->after('city');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->string('avatar_path')->nullable()->after('lng');
            $table->boolean('notify_email')->default(true)->after('avatar_path');
            $table->boolean('notify_push')->default(false)->after('notify_email');
            $table->decimal('rating_avg', 3, 2)->default(0)->after('notify_push');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
            $table->timestamp('blocked_at')->nullable()->after('rating_count');
            $table->unsignedBigInteger('legacy_user_id')->nullable()->unique()->after('blocked_at');
            $table->unsignedBigInteger('legacy_printer_id')->nullable()->unique()->after('legacy_user_id');
            $table->unsignedBigInteger('legacy_designer_id')->nullable()->unique()->after('legacy_printer_id');
        });

        Schema::create('oauth_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_id', 191);
            $table->timestamps();
            $table->unique(['provider', 'provider_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);                    // customer | printer | designer | admin
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('oauth_identities');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'phone_verified_at', 'locale', 'country', 'zip', 'city', 'lat', 'lng', 'avatar_path',
                'notify_email', 'notify_push', 'rating_avg', 'rating_count', 'blocked_at', 'legacy_user_id', 'legacy_printer_id', 'legacy_designer_id']);
        });
    }
};
