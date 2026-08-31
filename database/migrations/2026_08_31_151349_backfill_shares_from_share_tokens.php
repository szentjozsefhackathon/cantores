<?php

use App\Models\Folder;
use App\Models\MusicPlan;
use App\Models\Score;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Moves every existing secret link into the `shares` table, preserving the token so
 * that links already handed out keep working.
 *
 * This includes the score tokens that folder and plan sharing used to mint
 * automatically. Those were never deliberate shares, but they cannot be told apart
 * from deliberate ones in the data, so they are carried over rather than silently
 * broken — the link manager is what lets owners see and revoke them.
 *
 * The `share_token` columns are left in place, unused, and dropped in a later
 * migration once this has been verified in production.
 */
return new class extends Migration
{
    /**
     * @var array<class-string, string>
     */
    private const SOURCES = [
        Score::class => 'scores',
        Folder::class => 'folders',
        MusicPlan::class => 'music_plans',
    ];

    public function up(): void
    {
        $now = now();
        $seen = [];

        foreach (self::SOURCES as $model => $table) {
            DB::table($table)
                ->whereNotNull('share_token')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($model, $now, &$seen): void {
                    $inserts = [];

                    foreach ($rows as $row) {
                        $token = $row->share_token;

                        // Tokens were unique per table, not across them.
                        while (isset($seen[$token])) {
                            $token = Str::random(32);
                        }
                        $seen[$token] = true;

                        $inserts[] = [
                            'user_id' => $row->user_id,
                            'shareable_type' => $model,
                            'shareable_id' => $row->id,
                            'token' => $token,
                            'allow_download' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($inserts !== []) {
                        DB::table('shares')->insert($inserts);
                    }
                });
        }
    }

    public function down(): void
    {
        DB::table('shares')->truncate();
    }
};
