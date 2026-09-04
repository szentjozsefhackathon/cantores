<?php

use App\Enums\ScorePublicationStatus;
use App\Models\ScorePublication;
use App\Services\ScoreVersionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives every already-approved publication the snapshot it would have had.
 *
 * Without this, the first edit to a score approved before versioning existed
 * would fall through to the old behaviour and take it off the public shelf —
 * which is precisely what versioning is here to stop. The snapshot is taken from
 * the live score, which for an unedited approved score is what the reviewer read.
 */
return new class extends Migration
{
    public function up(): void
    {
        $versions = app(ScoreVersionService::class);

        ScorePublication::query()
            ->where('status', ScorePublicationStatus::Approved)
            ->whereNull('approved_version_id')
            ->with('score')
            ->chunkById(100, function ($publications) use ($versions): void {
                foreach ($publications as $publication) {
                    if ($publication->score === null) {
                        continue;
                    }

                    $version = $versions->snapshot($publication->score);

                    $publication->forceFill([
                        'approved_version_id' => $version->getKey(),
                        'submitted_version_id' => $version->getKey(),
                        'approved_fingerprint' => $version->fingerprint(),
                    ])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        DB::table('score_publications')->update([
            'approved_version_id' => null,
            'submitted_version_id' => null,
        ]);
    }
};
