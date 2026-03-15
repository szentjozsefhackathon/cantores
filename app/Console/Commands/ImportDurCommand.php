<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicTag;
use App\Models\MusicUrl;
use App\Models\User;
use App\MusicUrlLabel;
use Illuminate\Console\Command;

class ImportDurCommand extends Command
{
    protected $signature = 'cantores:import-dur
                            {--user= : User ID or email to own imported records (required)}';

    protected $description = 'Import music from DÚR könyv (dur.json) into the database. Updates existing songs if found.';

    public function handle(): int
    {
        // 1. Resolve user
        $userOption = $this->option('user');
        if ($userOption === null) {
            $this->error('The --user option is required. Pass a user ID or email.');

            return self::FAILURE;
        }

        $user = is_numeric($userOption)
            ? User::find((int) $userOption)
            : User::where('email', $userOption)->first();

        if (! $user) {
            $this->error("User not found: {$userOption}");

            return self::FAILURE;
        }

        $userId = $user->id;

        // 2. Load dur.json
        $jsonPath = base_path('import/dur.json');
        if (! file_exists($jsonPath)) {
            $this->error("dur.json not found at: {$jsonPath}");

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (! is_array($data)) {
            $this->error('Failed to parse dur.json.');

            return self::FAILURE;
        }

        // Flatten all songs from all letter groups
        $songs = [];
        foreach ($data as $group) {
            foreach ($group['dal'] as $song) {
                $songs[] = $song;
            }
        }

        $this->info('Loaded '.count($songs).' songs from dur.json.');

        // 3. Load tag mapping
        $tagMapPath = base_path('import/dur_tag_map.json');
        if (! file_exists($tagMapPath)) {
            $this->error("Tag mapping file not found at: {$tagMapPath}");
            $this->error('Create a JSON file mapping raw csoport values to MusicTag names, e.g.:');
            $this->line('  { "nagybojt": "Nagyböjt", "punkosd": "Pünkösd" }');

            return self::FAILURE;
        }

        $tagMap = json_decode(file_get_contents($tagMapPath), true);
        if (! is_array($tagMap)) {
            $this->error('Failed to parse dur_tag_map.json.');

            return self::FAILURE;
        }

        // 4. Pre-flight tag check
        $usedRawTags = [];
        foreach ($songs as $song) {
            foreach (array_merge($song['csoport'] ?? [], $song['csoport_2'] ?? []) as $raw) {
                $usedRawTags[$raw] = true;
            }
        }

        // Only check tags that are actually mapped
        $requiredMappedNames = [];
        foreach (array_keys($usedRawTags) as $raw) {
            if (isset($tagMap[$raw])) {
                $requiredMappedNames[$tagMap[$raw]] = true;
            }
        }
        $requiredMappedNames = array_keys($requiredMappedNames);

        if (! empty($requiredMappedNames)) {
            $existingTagNames = MusicTag::whereIn('name', $requiredMappedNames)->pluck('name')->all();
            $missingTags = array_diff($requiredMappedNames, $existingTagNames);

            if (! empty($missingTags)) {
                $this->error('The following MusicTag records are missing from the database:');
                foreach ($missingTags as $missing) {
                    $this->line("  - {$missing}");
                }

                return self::FAILURE;
            }

            $tagsByName = MusicTag::whereIn('name', $requiredMappedNames)->get()->keyBy('name');
        } else {
            $tagsByName = collect();
        }

        // 5. Find or create DÚR collection
        $collection = Collection::where('abbreviation', 'DÚR')->first();
        if (! $collection) {
            $collection = Collection::create([
                'title' => 'DÚR könyv',
                'abbreviation' => 'DÚR',
                'user_id' => $userId,
                'is_private' => false,
            ]);
            $this->info("Created DÚR collection (ID: {$collection->id}).");
        } else {
            $this->info("Using existing DÚR collection (ID: {$collection->id}).");
        }

        // 6. Import each song
        $created = 0;
        $updated = 0;

        $this->withProgressBar($songs, function (array $song) use ($collection, $userId, $tagMap, $tagsByName, &$created, &$updated) {
            [$title, $number] = $this->parseDalCime($song['dal_cime']);

            // Find existing music: first by order_number in DÚR collection, then by title
            $music = null;
            if ($number !== null) {
                $music = $collection->music()->wherePivot('order_number', $number)->first();
            }
            if (! $music) {
                $music = Music::where('title', $title)->first();
            }

            if ($music) {
                $music->update([
                    'title' => $title,
                    'user_id' => $userId,
                    'is_private' => false,
                ]);
                $updated++;
            } else {
                $music = Music::create([
                    'title' => $title,
                    'user_id' => $userId,
                    'is_private' => false,
                ]);
                $created++;
            }

            // Attach to DÚR collection
            $alreadyAttached = $music->collections()->where('collections.id', $collection->id)->exists();
            if (! $alreadyAttached) {
                $music->collections()->attach($collection->id, [
                    'order_number' => $number,
                    'user_id' => $userId,
                ]);
            } else {
                $music->collections()->updateExistingPivot($collection->id, [
                    'order_number' => $number,
                    'user_id' => $userId,
                ]);
            }

            // Sync URLs
            $this->syncUrl($music->id, MusicUrlLabel::SheetMusic->value, $song['pdf'] ?? null, $userId);
            $this->syncUrl($music->id, MusicUrlLabel::Text->value, $song['txt'] ?? null, $userId);
            $this->syncUrl($music->id, MusicUrlLabel::Audio->value, ($song['zene'] ?: null) ?? null, $userId);

            // Attach tags
            $rawTags = array_merge($song['csoport'] ?? [], $song['csoport_2'] ?? []);
            $tagIds = [];
            foreach ($rawTags as $raw) {
                $mappedName = $tagMap[$raw] ?? null;
                if ($mappedName && isset($tagsByName[$mappedName])) {
                    $tagIds[] = $tagsByName[$mappedName]->id;
                }
            }
            if (! empty($tagIds)) {
                $music->tags()->syncWithoutDetaching($tagIds);
            }
        });

        $this->newLine();
        $this->info("Done. Created: {$created}, Updated: {$updated}.");

        return self::SUCCESS;
    }

    /**
     * Parse dal_cime into [title, number|null].
     *
     * Handles formats:
     *   "A megfeszített Jézushoz - 165."  →  ["A megfeszített Jézushoz", "165"]
     *   "Készítsétek az Úrnak útját - 160/B"  →  ["...", "160/B"]
     *   "Látod, újra este van 188."  →  ["Látod, újra este van", "188"]
     *
     * @return array{0: string, 1: string|null}
     */
    private function parseDalCime(string $dalCime): array
    {
        // Primary: "Title - 165." or "Title - 160/B" or "Title - 160/B."
        if (preg_match('/^(.*?) - (\d+(?:\/[A-Z])?)\.?$/', $dalCime, $m)) {
            return [trim($m[1]), $m[2]];
        }

        // Fallback: "Title 188." (number without dash separator)
        if (preg_match('/^(.*?) (\d+(?:\/[A-Z])?)\.?$/', $dalCime, $m)) {
            return [trim($m[1]), $m[2]];
        }

        // No number found
        return [trim($dalCime), null];
    }

    /**
     * Create or update a single MusicUrl record by (music_id, label).
     */
    private function syncUrl(int $musicId, string $label, ?string $url, int $userId): void
    {
        if (empty($url)) {
            return;
        }

        $existing = MusicUrl::where('music_id', $musicId)->where('label', $label)->first();
        if ($existing) {
            $existing->update(['url' => $url]);
        } else {
            MusicUrl::create([
                'music_id' => $musicId,
                'label' => $label,
                'url' => $url,
                'user_id' => $userId,
            ]);
        }
    }
}
