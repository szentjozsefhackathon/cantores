<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('score_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 16);

            // The basis: why cantores.hu may publish this at all.
            $table->string('license', 32);
            // What the visitor may do, when the basis does not say so itself.
            $table->string('outbound_license', 32)->nullable();
            $table->string('license_version', 8)->nullable();
            $table->string('attribution_line')->nullable();

            // Provenance. Deliberately not encrypted, unlike score_urls.url:
            // this is published on purpose.
            $table->text('source_url')->nullable();
            $table->string('source_title')->nullable();
            $table->smallInteger('composer_death_year')->nullable();
            // Not a year: a nominator rarely knows when the engraving in front
            // of them was printed, but can say whether they made it themselves
            // or took it from an edition old enough to be free.
            $table->boolean('edition_is_free')->default(false);
            $table->text('rights_note')->nullable();
            $table->text('permission_evidence')->nullable();

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->boolean('self_approved')->default(false);

            $table->timestamp('published_at')->nullable();
            $table->timestamp('unpublished_at')->nullable();
            $table->text('takedown_reason')->nullable();

            // sha256 over the published files' checksums as they stood at
            // approval, so replacing the bytes behind an approved file cannot
            // slip past review.
            $table->string('approved_fingerprint', 64)->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_publications');
    }
};
