<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Genre;
use App\Models\Music;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MusicMergerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Music $leftMusic;
    protected Music $rightMusic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create test data
        $genre = Genre::factory()->create(['name' => 'organist']);
        $collection1 = Collection::factory()->create(['user_id' => $this->user->id]);
        $collection2 = Collection::factory()->create(['user_id' => $this->user->id]);

        $this->leftMusic = Music::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Left Music',
            'subtitle' => 'Left Subtitle',
            'custom_id' => 'LEFT123',
            'is_private' => false,
        ]);

        $this->rightMusic = Music::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Right Music',
            'subtitle' => 'Right Subtitle',
            'custom_id' => 'RIGHT456',
            'is_private' => true,
        ]);

        // Attach relationships
        $this->leftMusic->genres()->attach($genre);
        $this->rightMusic->genres()->attach($genre);

        $this->leftMusic->collections()->attach($collection1, [
            'page_number' => 5,
            'order_number' => 1,
        ]);

        $this->rightMusic->collections()->attach($collection1, [
            'page_number' => 10,
            'order_number' => 2,
        ]);

        $this->rightMusic->collections()->attach($collection2, [
            'page_number' => 3,
            'order_number' => 1,
        ]);
    }

    /** @test */
    public function merge_page_loads()
    {
        $this->get(route('music-merger'))
            ->assertOk()
            ->assertSee('Merge Music Pieces');
    }

    /** @test */
    public function can_search_and_select_music()
    {
        Livewire::test('editor.music-merger')
            ->set('leftSearch', 'Left')
            ->assertSee('Left Music')
            ->call('selectLeftMusic', $this->leftMusic->id)
            ->assertSet('leftMusicId', $this->leftMusic->id)
            ->assertSet('leftMusic.title', 'Left Music');
    }

    /** @test */
    public function cannot_select_same_music_for_both_sides()
    {
        Livewire::test('editor.music-merger')
            ->call('selectLeftMusic', $this->leftMusic->id)
            ->call('selectRightMusic', $this->leftMusic->id)
            ->assertDispatched('error')
            ->assertSet('rightMusicId', null);
    }

    /** @test */
    public function compare_shows_conflicts_when_fields_differ()
    {
        Livewire::test('editor.music-merger')
            ->call('selectLeftMusic', $this->leftMusic->id)
            ->call('selectRightMusic', $this->rightMusic->id)
            ->assertSet('showComparison', true)
            ->assertSet('conflicts.title', [
                'left' => 'Left Music',
                'right' => 'Right Music',
                'resolution' => 'left',
            ])
            ->assertSet('conflicts.is_private', [
                'left' => false,
                'right' => true,
                'resolution' => 'false',
            ]);
    }

    /** @test */
    public function merged_data_uses_left_values_by_default()
    {
        Livewire::test('editor.music-merger')
            ->call('selectLeftMusic', $this->leftMusic->id)
            ->call('selectRightMusic', $this->rightMusic->id)
            ->assertSet('mergedTitle', 'Left Music')
            ->assertSet('mergedSubtitle', 'Left Subtitle')
            ->assertSet('mergedCustomId', 'LEFT123');
    }

    /** @test */
    public function privacy_conflict_resolves_to_false()
    {
        Livewire::test('editor.music-merger')
            ->call('selectLeftMusic', $this->leftMusic->id)
            ->call('selectRightMusic', $this->rightMusic->id)
            ->assertSet('mergedIsPrivate', false); // Should be false (public) due to conflict
    }

    /** @test */
    public function collections_merge_with_conflict_detection()
    {
        Livewire::test('editor.music-merger')
            ->call('selectLeftMusic', $this->leftMusic->id)
            ->call('selectRightMusic', $this->rightMusic->id);

        $component = Livewire::test('editor.music-merger')
            ->call('selectLeftMusic', $this->leftMusic->id)
            ->call('selectRightMusic', $this->rightMusic->id);

        // Should have 2 merged collections (one with conflict)
        $this->assertCount(2, $component->get('mergedCollections'));

        // Find the conflicted collection
        $conflicted = collect($component->get('mergedCollections'))
            ->firstWhere('conflict', true);

        $this->assertNotNull($conflicted);
        $this->assertEquals(5, $conflicted['pivot']->page_number); // Left's page number
    }

    /** @test */
    public function genres_merge_as_union()
    {
        $genre2 = Genre::factory()->create(['name' => 'guitarist']);
        $this->rightMusic->genres()->attach($genre2);

        Livewire::test('editor.music-merger')
            ->call('selectLeftMusic', $this->leftMusic->id)
            ->call('selectRightMusic', $this->rightMusic->id)
            ->assertCount(2, 'mergedGenres'); // Both genres
    }

    /** @test */
    public function save_merge_updates_left_and_deletes_right()
    {
        // Create a music plan slot assignment referencing right music
        $assignment = \App\Models\MusicPlanSlotAssignment::factory()->create([
            'music_id' => $this->rightMusic->id,
        ]);

        Livewire::test('editor.music-merger')
            ->call('selectLeftMusic', $this->leftMusic->id)
            ->call('selectRightMusic', $this->rightMusic->id)
            ->set('mergedTitle', 'Merged Title')
            ->set('mergedSubtitle', 'Merged Subtitle')
            ->call('saveMerge')
            ->assertRedirect(route('music-editor', ['music' => $this->leftMusic->id]));

        // Verify left music updated
        $this->leftMusic->refresh();
        $this->assertEquals('Merged Title', $this->leftMusic->title);
        $this->assertEquals($this->user->id, $this->leftMusic->user_id); // Owner changed to current user

        // Verify right music deleted
        $this->assertDatabaseMissing('musics', ['id' => $this->rightMusic->id]);

        // Verify assignment updated to left music
        $this->assertDatabaseHas('music_plan_slot_assignments', [
            'id' => $assignment->id,
            'music_id' => $this->leftMusic->id,
        ]);
    }

    /** @test */
    public function user_without_permission_cannot_merge()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        // Try to merge music owned by different user
        Livewire::test('editor.music-merger')
            ->call('selectLeftMusic', $this->leftMusic->id)
            ->assertDispatched('error'); // Should fail authorization
    }

    /** @test */
    public function empty_vs_non_empty_not_treated_as_conflict()
    {
        $music1 = Music::factory()->create([
            'user_id' => $this->user->id,
            'subtitle' => null,
            'custom_id' => 'ID123',
        ]);

        $music2 = Music::factory()->create([
            'user_id' => $this->user->id,
            'subtitle' => 'Has Subtitle',
            'custom_id' => null,
        ]);

        Livewire::test('editor.music-merger')
            ->call('selectLeftMusic', $music1->id)
            ->call('selectRightMusic', $music2->id);

        // Should not have conflicts for subtitle/custom_id (empty vs non-empty)
        $this->assertArrayNotHasKey('subtitle', Livewire::test('editor.music-merger')
            ->call('selectLeftMusic', $music1->id)
            ->call('selectRightMusic', $music2->id)
            ->get('conflicts'));

        // Merged values should use non-empty where available
        $component = Livewire::test('editor.music-merger')
            ->call('selectLeftMusic', $music1->id)
            ->call('selectRightMusic', $music2->id);

        $this->assertEquals('Has Subtitle', $component->get('mergedSubtitle')); // From right
        $this->assertEquals('ID123', $component->get('mergedCustomId')); // From left
    }
}