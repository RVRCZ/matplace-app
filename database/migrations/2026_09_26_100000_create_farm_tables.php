<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public print farm. Everything that describes a machine, a material or a price is a row, so another printer or
 * PETG is data entry. Agents (one per site, many printers each) talk to us with outgoing requests only; the
 * command queue and the print job rows are what a driver needs, whichever it is (Moonraker now, others later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('farm_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('token_hash', 64)->unique();                  // sha256 of the bearer token; the token itself is shown once
            $table->string('version', 40)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('farm_materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                        // PLA, PETG…
            $table->string('name', 80);
            $table->string('filament_profile', 120);                     // slicer profile file
            $table->json('filament_overrides')->nullable();
            $table->decimal('density', 5, 3)->default(1.24);
            $table->decimal('price_per_gram', 8, 4);                     // without VAT
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('farm_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_material_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('hex', 7)->default('#cccccc');
            $table->string('photo_path')->nullable();                    // real photo of a print in this filament (public disk)
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('farm_printers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('model', 80);                                 // Anycubic Kobra S1
            $table->string('key', 40)->unique();                         // what the agent calls this printer in its config
            $table->foreignId('farm_agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 10)->default('manual');               // manual = operator sends the G-code | agent = automatic
            $table->boolean('enabled')->default(true);

            // slicer profile as data
            $table->decimal('bed_x', 6, 1);
            $table->decimal('bed_y', 6, 1);
            $table->decimal('bed_z', 6, 1);
            $table->decimal('nozzle_mm', 3, 2)->default(0.4);
            $table->string('machine_profile', 120);
            $table->json('process_profiles');                            // quality key → process profile file
            $table->json('machine_overrides')->nullable();
            $table->json('process_overrides')->nullable();

            // calibration and pricing
            $table->decimal('time_factor', 5, 3)->default(1);
            $table->decimal('weight_factor', 5, 3)->default(1);
            $table->decimal('hourly_rate', 8, 2)->nullable();            // null = the farm default

            // live state
            $table->boolean('bed_clear')->default(false);                // operator confirmed an empty plate; cleared by every start
            $table->timestamp('bed_cleared_at')->nullable();
            $table->string('state', 12)->default('unknown');             // idle | printing | paused | error | offline | unknown
            $table->json('telemetry')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('snapshot_path')->nullable();
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamp('offline_notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('farm_printer_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_printer_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');                         // 0-based, the tool number in the G-code
            $table->foreignId('farm_color_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('remaining_g', 8, 1)->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['farm_printer_id', 'slot']);
        });

        Schema::create('farm_orders', function (Blueprint $table) {
            $table->id();
            $table->string('token', 32)->unique();
            $table->string('number', 20)->nullable()->unique();          // human number, assigned when paid
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('model_file_id')->constrained()->cascadeOnDelete();
            $table->string('status', 14)->index();
            $table->string('stage', 12)->nullable();                     // while `uploaded`: checking | orienting | slicing
            $table->string('error', 60)->nullable();                     // machine code, translated for the customer
            $table->text('error_detail')->nullable();

            // what the customer chose
            $table->string('quality', 12)->default('standard');
            $table->string('strength', 12)->default('standard');
            $table->decimal('unit_scale', 8, 3)->default(1);             // 1 = mm, 25.4 = inches, 1000 = metres…
            $table->foreignId('farm_material_id')->constrained();
            $table->foreignId('farm_color_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('farm_printer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('farm_printer_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery', 10)->default('pickup');
            $table->json('shipping_address')->nullable();
            $table->string('note', 500)->nullable();

            // what the system did — enough to reproduce the print
            $table->json('check')->nullable();                           // validation report incl. repair
            $table->json('orientation')->nullable();                     // rotation matrix + scores
            $table->string('print_stl_path')->nullable();                // repaired, scaled, oriented
            $table->string('gcode_path')->nullable();
            $table->string('gcode_sha256', 64)->nullable();
            $table->json('slice_params')->nullable();                    // profiles, overrides, engine, versions
            $table->json('slice_result')->nullable();
            $table->unsignedInteger('est_minutes')->nullable();
            $table->decimal('est_grams', 8, 1)->nullable();
            $table->decimal('est_meters', 8, 2)->nullable();
            $table->boolean('supports_used')->default(false);

            // money
            $table->json('price')->nullable();                           // full breakdown with the inputs used
            $table->decimal('price_total', 10, 2)->nullable();           // what the customer pays (with VAT)
            $table->string('currency', 3)->default('CZK');
            $table->string('terms_version', 20)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_ip', 45)->nullable();

            // life cycle
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('handed_at')->nullable();
            $table->string('tracking', 80)->nullable();

            // measured after the print, feeds the calibration of time_factor / weight_factor
            $table->unsignedInteger('actual_minutes')->nullable();
            $table->decimal('actual_grams', 8, 1)->nullable();
            $table->string('actual_source', 10)->nullable();             // agent | admin
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['farm_printer_id', 'status']);
        });

        Schema::create('farm_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_order_id')->constrained()->cascadeOnDelete();
            $table->string('from', 14)->nullable();
            $table->string('to', 14);
            $table->string('actor', 10);                                 // user | admin | agent | system
            $table->foreignId('actor_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('farm_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_printer_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot')->default(0);
            $table->string('status', 12)->default('pending');            // pending | sent | printing | paused | done | failed | cancelled | unknown
            $table->string('remote_filename', 160)->nullable();
            $table->decimal('progress', 5, 2)->default(0);               // 0–100
            $table->json('telemetry')->nullable();                       // last print_stats, temperatures
            $table->unsignedInteger('print_duration_s')->nullable();
            $table->decimal('filament_used_mm', 10, 1)->nullable();
            $table->string('message', 300)->nullable();
            $table->string('snapshot_path')->nullable();
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('farm_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_printer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_print_job_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 10);                                  // start | pause | resume | cancel
            $table->json('payload')->nullable();
            $table->string('status', 10)->default('pending');            // pending | sent | done | failed
            $table->string('result', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['farm_printer_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('gateway_ref', 120)->nullable();
            $table->string('purpose', 20)->default('credit_topup');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('CZK');
            $table->string('status', 10)->default('pending');            // pending | paid | failed | expired
            $table->timestamp('paid_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'gateway_ref']);
        });

        // Append-only ledger: the balance is the sum of amounts. Nothing here is ever updated or deleted.
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);                                  // topup | hold | capture | release | refund | adjust
            $table->decimal('amount', 10, 2);                            // effect on the spendable balance (+/−, 0 for capture)
            $table->string('currency', 3)->default('CZK');
            $table->foreignId('farm_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->unique(['payment_id', 'type']);                      // a webhook delivered twice credits once
        });
    }

    public function down(): void
    {
        foreach (['credit_transactions', 'payments', 'farm_commands', 'farm_print_jobs', 'farm_order_events', 'farm_orders',
            'farm_printer_slots', 'farm_printers', 'farm_colors', 'farm_materials', 'farm_agents', 'farm_settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
