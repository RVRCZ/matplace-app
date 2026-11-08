<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The home page's router (TODO #78, docs/AN.md §22): what a visitor handed in (a photo, a 3D file or words), what the
 * assistant decided (a tool with its parameters, a shape to be made, a person, or the calculator for a file) and what
 * it cost. `intake_requests` is the "a person takes it over" path: the e-mail the customer left, their consent, and
 * the operator's reply. Files of an intake live on the local disk under intake/<token>/ and go after 30 days
 * (matplace:prune); the rows stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intakes', function (Blueprint $table) {
            $table->id();
            $table->string('token', 24)->unique();
            $table->foreignId('anonymous_session_id')->nullable()->constrained('anonymous_sessions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 16);                               // photo | file | text
            $table->string('photo_path', 255)->nullable();            // the photo as uploaded (local disk, intake/<token>/original.<ext>)
            $table->string('photo_model_path', 255)->nullable();      // upright JPEG, long edge ≤ 2000 px: what the model and the tool pages get
            $table->foreignId('model_file_id')->nullable()->constrained('model_files')->nullOnDelete();
            $table->text('text')->nullable();
            $table->string('locale', 5)->default('cs');
            $table->string('route', 16)->nullable();                  // tool | shape | human | file
            $table->string('tool_key', 32)->nullable();
            $table->json('params')->nullable();                       // size_mm, colors, copies, text, place, kind
            $table->decimal('confidence', 3, 2)->nullable();
            $table->text('summary')->nullable();                      // the sentence for the customer, in their language
            $table->json('answer')->nullable();                       // the assistant's whole answer (summaries in three languages, questions, the route it meant)
            $table->boolean('wants_shape')->default(false);
            $table->string('model', 60)->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->decimal('cost_czk', 8, 4)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 16)->default('new');             // new | opened | human | closed
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('photos_pruned_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('intake_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intake_id')->constrained('intakes')->cascadeOnDelete();
            $table->string('email', 190);
            $table->string('name', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->timestamp('consent_at');
            $table->boolean('wants_shape')->default(false);
            $table->string('status', 16)->default('new');             // new | answered | closed
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('locale', 5)->default('cs');
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_requests');
        Schema::dropIfExists('intakes');
    }
};
