<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step E: the blog. Articles of the old site keep their addresses (/blog/{slug}); a language exists only where
 * the article has a title and a body in it (the old ones are Czech only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();   // blog_posts.id of the old site
            $table->string('slug', 160)->unique();
            $table->json('title');                       // per language
            $table->json('excerpt')->nullable();         // per language: the lead of the list and the meta description
            $table->json('body');                        // per language
            $table->string('format', 10)->default('markdown');   // markdown (written in the admin) | html (carried over, cleaned)
            $table->string('cover_path')->nullable();    // on the public disk, or a full address of a picture kept elsewhere
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name', 120)->nullable();
            $table->timestamp('published_at')->nullable()->index();   // null = a draft
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
