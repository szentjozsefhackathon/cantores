<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A loan that opens only for named people.
 *
 * The flag is stored separately from the list so that taking the last person off
 * a restricted loan leaves it open to nobody but the lender, rather than quietly
 * turning it into a link anyone may follow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->boolean('restricted')->default(false)->after('allow_download');
        });

        Schema::create('loan_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['loan_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_recipients');

        Schema::table('loans', function (Blueprint $table): void {
            $table->dropColumn('restricted');
        });
    }
};
