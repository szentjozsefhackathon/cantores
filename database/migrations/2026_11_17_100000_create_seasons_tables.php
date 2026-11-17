<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A choir's season: the dates it sings at, and who is coming to each.
 *
 * An event is a name and a date before it is anything liturgical — a rehearsal
 * on a Tuesday evening belongs on the same list as Midnight Mass — so the
 * celebration and the music plan are optional links rather than its identity.
 *
 * Members are names, not accounts: the people answering are choir members
 * following a link on their phones, and a sign-up form is the friction this
 * page exists to avoid. A member who happens to be signed in is tied to their
 * account so the page knows them on any device.
 *
 * An attachment is either one of the owner's booklets or plans, opened through
 * a loan the season keeps for it (`loan_id`), or a plain link to somewhere else
 * — a Drive folder, a recording.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('season_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->date('date');
            $table->time('time')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('celebration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('music_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['season_id', 'date', 'time']);
        });

        Schema::create('season_event_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_event_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('lendable');
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url', 2048)->nullable();
            $table->string('label', 150)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('season_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['season_id', 'name']);
        });

        Schema::create('season_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_member_id')->constrained()->cascadeOnDelete();
            $table->string('status', 8);
            $table->timestamps();

            $table->unique(['season_event_id', 'season_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_attendances');
        Schema::dropIfExists('season_members');
        Schema::dropIfExists('season_event_attachments');
        Schema::dropIfExists('season_events');
        Schema::dropIfExists('seasons');
    }
};
