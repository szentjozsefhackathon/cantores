<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\MusicCollection;
use App\Models\MusicUrl;
use App\Models\User;
use App\MusicUrlLabel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EmmetLinksCommand extends Command
{
    protected $signature = 'cantores:emmet-links
                            {--user= : User ID or email to own the created links (required)}
                            {--dry-run : Report what would be linked without writing anything}';

    protected $description = 'Link music in the Emmánuel Community collections to their song pages in Emmet (emmet.emmanuelkozosseg.hu).';

    public const BASE_URL = 'https://emmet.emmanuelkozosseg.hu';

    /**
     * Emmet book IDs mapped to the abbreviation of the collection holding the same songs.
     *
     * @var array<string, string>
     */
    public const BOOKS = [
        'emm_hu' => 'JÉL',
    ];

    public function handle(): int
    {
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

        $response = Http::timeout(30)->get(self::BASE_URL.'/songs.json');
        if ($response->failed() || ! is_array($response->json('songs'))) {
            $this->error('Could not download the Emmet song list.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach (self::BOOKS as $bookId => $abbreviation) {
            $collection = Collection::where('abbreviation', $abbreviation)->first();
            if (! $collection) {
                $this->warn("Collection {$abbreviation} not found, skipping Emmet book {$bookId}.");

                continue;
            }

            $this->linkBook($bookId, $collection, $this->songsOf($response->json('songs'), $bookId), $user, $dryRun);
        }

        return self::SUCCESS;
    }

    /**
     * The songs of one Emmet book, keyed by the lowercase song number, which is how Emmet looks them up.
     *
     * `recordingLang` is the lyrics language whose recordings tab to link to, or null when the song has no recordings.
     *
     * @param  array<int, array{books?: array<int, array{id: string, number: string}>, lyrics?: array<int, array{lang: string}>, records?: array<int, array{lang?: string}>}>  $songs
     * @return array<string, array{number: string, recordingLang: string|null}>
     */
    private function songsOf(array $songs, string $bookId): array
    {
        $bookSongs = [];

        foreach ($songs as $song) {
            foreach ($song['books'] ?? [] as $book) {
                if ($book['id'] === $bookId) {
                    $number = trim($book['number']);
                    $bookSongs[mb_strtolower($number)] = [
                        'number' => $number,
                        'recordingLang' => $this->recordingLangOf($song),
                    ];
                }
            }
        }

        return $bookSongs;
    }

    /**
     * Emmet lists every recording of a song on the recordings tab of any of its lyrics languages,
     * so prefer the language of a recording when the song has lyrics in it.
     *
     * @param  array{lyrics?: array<int, array{lang: string}>, records?: array<int, array{lang?: string}>}  $song
     */
    private function recordingLangOf(array $song): ?string
    {
        if (empty($song['records'])) {
            return null;
        }

        $lyricsLangs = array_column($song['lyrics'] ?? [], 'lang');
        $recordLangs = array_filter(array_column($song['records'], 'lang'));

        return array_values(array_intersect($recordLangs, $lyricsLangs))[0]
            ?? $lyricsLangs[0]
            ?? array_values($recordLangs)[0]
            ?? null;
    }

    /**
     * @param  array<string, array{number: string, recordingLang: string|null}>  $emmetSongs
     */
    private function linkBook(string $bookId, Collection $collection, array $emmetSongs, User $user, bool $dryRun): void
    {
        $created = 0;
        $existing = 0;
        $unmatched = [];

        $pivots = MusicCollection::where('collection_id', $collection->id)
            ->whereNotNull('order_number')
            ->orderBy('order_number')
            ->get();

        foreach ($pivots as $pivot) {
            $emmetSong = $emmetSongs[mb_strtolower(trim($pivot->order_number))] ?? null;
            if ($emmetSong === null) {
                $unmatched[] = $pivot->order_number;

                continue;
            }

            $links = [MusicUrlLabel::Text->value => self::songUrl($bookId, $emmetSong['number'])];
            if ($emmetSong['recordingLang'] !== null) {
                $links[MusicUrlLabel::Audio->value] = self::recordingsUrl($bookId, $emmetSong['number'], $emmetSong['recordingLang']);
            }

            foreach ($links as $label => $url) {
                if (MusicUrl::where('music_id', $pivot->music_id)->where('url', $url)->exists()) {
                    $existing++;

                    continue;
                }

                if (! $dryRun) {
                    MusicUrl::create([
                        'music_id' => $pivot->music_id,
                        'url' => $url,
                        'label' => $label,
                        'user_id' => $user->id,
                    ]);
                }
                $created++;
            }
        }

        $verb = $dryRun ? 'would be created' : 'created';
        $this->info("{$collection->abbreviation} ↔ Emmet {$bookId}: {$created} links {$verb}, {$existing} already present.");

        if ($unmatched !== []) {
            $this->warn('Not found in Emmet: '.implode(', ', $unmatched));
        }
    }

    /**
     * The recordings tab of a song's Emmet page, e.g. /emm-hu/1/hu/rec/.
     */
    public static function recordingsUrl(string $bookId, string $songNumber, string $lang): string
    {
        return self::songUrl($bookId, $songNumber).'/'.rawurlencode($lang).'/rec/';
    }

    /**
     * The Emmet page of a song; Emmet writes underscores of book IDs as hyphens in its paths.
     */
    public static function songUrl(string $bookId, string $songNumber): string
    {
        return self::BASE_URL.'/'.rawurlencode(str_replace('_', '-', $bookId)).'/'.rawurlencode($songNumber);
    }
}
