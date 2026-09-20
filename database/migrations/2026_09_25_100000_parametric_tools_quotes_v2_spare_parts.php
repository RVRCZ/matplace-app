<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parametric tools remember their parameters; inquiries carry a colour and can be a spare-part request without a model;
 * quotes get an internal cost sheet, shipping, colour, versions, change requests and a revocable, longer link token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_files', function (Blueprint $table) {
            $table->json('tool_params')->nullable()->after('origin_ref');
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('kind', 12)->default('print')->after('token');       // print | spare_part
            $table->string('color', 40)->nullable()->after('material_code');
            $table->json('details')->nullable()->after('summary');             // spare part: dimensions, use, load, photos
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->string('token', 40)->change();                              // new links are 32 characters
            $table->unsignedSmallInteger('version')->default(1)->after('status');
            $table->unsignedSmallInteger('accepted_version')->nullable()->after('version');
            $table->json('cost')->nullable()->after('lines');                   // internal only: never shown to the customer
            $table->string('color', 40)->nullable()->after('title');
            $table->string('shipping_label', 120)->nullable()->after('total');
            $table->decimal('shipping_price', 10, 2)->default(0)->after('shipping_label');
            $table->timestamp('revoked_at')->nullable()->after('declined_at');
            $table->text('change_request')->nullable()->after('revoked_at');
            $table->timestamp('change_requested_at')->nullable()->after('change_request');
        });

        Schema::create('quote_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->json('snapshot');                                           // customer-facing content exactly as sent
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_ip', 45)->nullable();
            $table->text('change_request')->nullable();
            $table->timestamps();
            $table->unique(['quote_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_versions');
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['version', 'accepted_version', 'cost', 'color', 'shipping_label', 'shipping_price', 'revoked_at', 'change_request', 'change_requested_at']);
        });
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn(['kind', 'color', 'details']);
        });
        Schema::table('model_files', function (Blueprint $table) {
            $table->dropColumn('tool_params');
        });
    }
};
