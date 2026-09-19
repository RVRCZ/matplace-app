<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Printer role: profile, machines, materials, pricing profiles, portfolio. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name', 120);
            $table->string('slug', 140)->unique();
            $table->string('company', 160)->nullable();
            $table->string('ico', 12)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('pickup_address')->nullable();
            $table->json('delivery_options')->nullable();          // ["pickup","zasilkovna","courier"]
            $table->unsignedSmallInteger('lead_time_days')->default(5);
            $table->string('capacity', 10)->default('open');       // open | busy | paused
            $table->date('next_available_at')->nullable();
            $table->text('bio')->nullable();
            $table->json('regions')->nullable();                    // legacy kraje, used until zip/geo is complete
            $table->boolean('visible')->default(true);
            $table->unsignedBigInteger('legacy_printer_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('printer_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('technology', 10)->default('fdm');     // fdm | resin
            $table->unsignedSmallInteger('bed_x')->nullable();
            $table->unsignedSmallInteger('bed_y')->nullable();
            $table->unsignedSmallInteger('bed_z')->nullable();
            $table->decimal('nozzle_mm', 3, 2)->nullable();
            $table->unsignedSmallInteger('count')->default(1);
            $table->timestamps();
        });

        Schema::create('printer_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('material_code', 10);                    // config/materials.php key
            $table->decimal('price_per_gram', 6, 2)->nullable();    // null = pricing profile default
            $table->json('colors')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->timestamps();
            $table->unique(['printer_profile_id', 'material_code']);
        });

        Schema::create('pricing_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80)->default('Standard');
            $table->boolean('is_default')->default(false);
            $table->decimal('hourly_rate', 8, 2)->default(60);
            $table->decimal('price_per_gram', 6, 2)->default(2);
            $table->decimal('setup_fee', 8, 2)->default(0);
            $table->decimal('margin_pct', 5, 2)->default(0);
            $table->decimal('min_price', 8, 2)->default(0);
            $table->unsignedSmallInteger('lead_time_days')->default(5);
            $table->decimal('express_pct', 5, 2)->default(0);
            $table->json('qty_discounts')->nullable();              // [{from:5,pct:10}]
            $table->json('finishing')->nullable();                  // [{name:"Broušení",price:50}]
            $table->timestamps();
        });

        Schema::create('printer_portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('photo_path');
            $table->string('title', 160)->nullable();
            $table->string('material_code', 10)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role_rated', 10)->default('printer');  // printer | designer
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->string('status', 10)->default('approved');
            $table->unsignedBigInteger('inquiry_id')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('printer_portfolio_items');
        Schema::dropIfExists('pricing_profiles');
        Schema::dropIfExists('printer_materials');
        Schema::dropIfExists('printer_machines');
        Schema::dropIfExists('printer_profiles');
    }
};
