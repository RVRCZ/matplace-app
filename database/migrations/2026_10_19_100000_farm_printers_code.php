<?php

use App\Models\FarmPrinter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The short code of a machine: farm letter + printer number ("U3"). A spool is that code and its slot ("U33" = farm
 * U, printer 3, slot 3): the customer sees it next to every colour, the operator at every job. Existing machines get
 * the code their name suggests ("Farm U #3" → U3); the admin can change it on the printer's page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_printers', function (Blueprint $table) {
            $table->string('code', 8)->nullable()->unique()->after('name');
        });
        $taken = [];
        foreach (DB::table('farm_printers')->orderBy('id')->get(['id', 'name']) as $row) {
            $code = FarmPrinter::suggestCode((string) $row->name, $taken);
            $taken[] = $code;
            DB::table('farm_printers')->where('id', $row->id)->update(['code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('farm_printers', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
