<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A farm is a place with an address (Roman, 12 Nov 2026: U = Útušice, B = Bory in Plzeň, S = Stod), not the agent that
 * happens to run its printers. Printers get `farm_id`; the three farms are seeded with the street and the postcode left
 * for the admin, and the printers are assigned once from the agent that runs them today ("Agent1 S1" → U,
 * "Farma B" → B). Printers without an agent stay unassigned; the printer's code is NOT used (U3 stands at farm B).
 * docs/AQ.md.
 */
return new class extends Migration
{
    /** agent name → farm code, the state of 12 Nov 2026 */
    private const AGENT_FARM = ['Agent1 S1' => 'U', 'Farma B' => 'B'];

    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name', 80);
            $table->string('street', 120)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('zip', 16)->nullable();
            $table->string('country', 2)->default('CZ');
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('sort')->default(100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
        Schema::table('farm_printers', function (Blueprint $table) {
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
        });

        $now = now();
        foreach ([['U', 'Farma U', 'Útušice', 10], ['B', 'Farma B', 'Plzeň (Bory)', 20], ['S', 'Farma S', 'Stod', 30]] as [$code, $name, $city, $sort]) {
            DB::table('farms')->insert(['code' => $code, 'name' => $name, 'city' => $city, 'country' => 'CZ', 'sort' => $sort, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (self::AGENT_FARM as $agent => $code) {
            $agentIds = DB::table('farm_agents')->where('name', $agent)->pluck('id')->all();
            if ($agentIds) {
                DB::table('farm_printers')->whereIn('farm_agent_id', $agentIds)->update(['farm_id' => DB::table('farms')->where('code', $code)->value('id')]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('farm_printers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('farm_id');
        });
        Schema::dropIfExists('farms');
    }
};
