<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Designers: anybody with an account can switch on a public portfolio, import the cards of their models from
 * Printables and MakerWorld (metadata only, the files they upload themselves) and earn a reward for every piece the
 * farm prints. Plus two logs the portfolio already needs: `events` (visits, referrals) and `ai_calls` (translations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name', 120);
            $table->string('slug', 140)->unique();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();                   // public disk
            $table->string('cover_path')->nullable();
            $table->json('links')->nullable();                           // printables, makerworld, instagram, web
            // ownership of the profile on another site is proven by a token the designer puts there
            $table->string('printables_token', 32)->nullable();
            $table->string('printables_username', 120)->nullable();      // the handle in the profile address
            $table->string('printables_user_id', 40)->nullable();
            $table->timestamp('printables_verified_at')->nullable();
            $table->string('makerworld_token', 32)->nullable();
            $table->string('makerworld_handle', 120)->nullable();
            $table->string('makerworld_uid', 40)->nullable();
            $table->timestamp('makerworld_verified_at')->nullable();
            $table->decimal('default_royalty_czk', 8, 2)->default(25);   // reward per printed piece, new cards start with it
            $table->boolean('visible')->default(false);                  // public at /d/{slug}
            $table->timestamp('published_at')->nullable();               // the first time it went public (by its first card, or by hand)
            // payout details, not shown anywhere yet (payouts are a later step)
            $table->string('iban', 40)->nullable();
            $table->string('iban_owner', 120)->nullable();
            $table->string('ico', 20)->nullable();
            $table->string('dic', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('designer_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->json('description')->nullable();                     // {cs: …, en: …, es: …}
            $table->string('source', 20)->default('manual');             // printables | makerworld | manual
            $table->string('source_locale', 5)->nullable();              // language the source text was written in
            $table->string('external_url', 400)->nullable()->unique();   // the card at the source; one card per address
            $table->string('external_id', 40)->nullable();
            $table->string('license_source', 200)->nullable();           // the licence as the source words it
            $table->boolean('is_remix')->default(false);
            $table->string('remix_source_url', 500)->nullable();
            $table->timestamp('remix_confirmed_at')->nullable();         // "the original allows commercial use and derivatives"
            $table->foreignId('model_file_id')->nullable()->constrained('model_files')->nullOnDelete();
            $table->decimal('royalty_czk', 8, 2)->default(25);
            $table->boolean('download_allowed')->default(false);
            $table->string('download_license', 20)->nullable();          // cc_by | cc_by_sa | cc_by_nc | cc0
            $table->timestamp('author_confirmed_at')->nullable();        // "I am the author and let matplace print it for customers"
            $table->boolean('visible')->default(true);
            $table->foreignId('catalog_model_id')->nullable()->constrained('catalog_models')->nullOnDelete();   // the same model in the inspiration catalogue
            $table->json('slice_summary')->nullable();                   // dims, grams, minutes, material of one piece
            $table->string('file_status', 12)->default('none');          // none | checking | ready | failed
            $table->json('file_check')->nullable();                      // what the model check said about the uploaded file
            $table->json('tags')->nullable();
            $table->json('source_files')->nullable();                    // file names at the source: they help to pair a zip with the cards
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->timestamps();
            $table->index(['designer_profile_id', 'visible']);
        });

        Schema::create('designer_model_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designer_model_id')->constrained()->cascadeOnDelete();
            $table->string('path');                                      // public disk; "<name>_s.webp" beside it is the small version
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();
        });

        Schema::create('designer_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designer_profile_id')->nullable()->constrained()->cascadeOnDelete();   // null = an admin's import into the inspiration catalogue
            $table->string('source', 20);
            $table->string('status', 12)->default('pending');            // pending | running | done
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('done')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('items');                                       // [{id, url, state: waiting|imported|skipped|failed, note, model_id}]
            $table->json('log')->nullable();
            $table->timestamps();
        });

        // An order of a designer's model remembers the card and the reward per piece as it was when ordered;
        // the reward is credited to the designer when the print is done (ledger type "royalty").
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->foreignId('designer_model_id')->nullable()->constrained('designer_models')->nullOnDelete();
            $table->decimal('royalty_czk', 8, 2)->nullable();            // per piece, frozen at the order (after the 30 % cap)
        });
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->string('type', 20)->change();                        // + royalty, royalty_reversal
            $table->foreignId('designer_model_id')->nullable()->constrained('designer_models')->nullOnDelete();
        });

        // First-party statistics: no extra cookie (the session id is the one every visitor already has), no personal data.
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
            $table->unsignedBigInteger('session_id')->nullable();        // anonymous_sessions.id
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 5)->nullable();
            $table->string('type', 30);                                  // view | ref_visit | upload | download | order_paid | …
            $table->string('subject_type', 30)->nullable();              // designer | designer_model | tool | …
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('source', 20)->nullable();                    // google | facebook | seznam | designer | direct | other
            $table->string('ref_slug', 140)->nullable();                 // the designer whose link brought the visitor
            $table->json('utm')->nullable();
            $table->json('meta')->nullable();
            $table->index(['type', 'created_at']);
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['ref_slug', 'created_at']);
            $table->index(['session_id', 'type']);
        });

        Schema::create('ai_calls', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
            $table->string('kind', 30);                                  // translate | describe | classify | generate | …
            $table->string('engine', 60);                                // model or provider that answered
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost_czk', 10, 4)->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('subject_type', 30)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designer_model_id');
        });
        Schema::table('farm_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designer_model_id');
            $table->dropColumn('royalty_czk');
        });
        foreach (['ai_calls', 'events', 'designer_imports', 'designer_model_images', 'designer_models', 'designer_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
