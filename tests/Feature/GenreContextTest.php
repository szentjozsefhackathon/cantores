<?php

use App\Facades\GenreContext;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

test('genre context returns null when no user and no session', function () {
    // Ensure no authenticated user
    Auth::logout();
    Session::forget('current_genre_id');

    expect(GenreContext::getId())->toBeNull();
    expect(GenreContext::get())->toBeNull();
    expect(GenreContext::hasGenre())->toBeFalse();
});

test('genre context returns user genre when authenticated', function () {
    $genre = Genre::where('name', 'organist')->firstOrFail();
    $user = User::factory()->create(['current_genre_id' => $genre->id]);

    $this->actingAs($user);

    expect(GenreContext::getId())->toBe($genre->id);
    expect(GenreContext::get()->id)->toBe($genre->id);
    expect(GenreContext::hasGenre())->toBeTrue();
    expect(GenreContext::label())->toBe($genre->label());
    expect(GenreContext::icon())->toBe($genre->icon());
    expect(GenreContext::color())->toBe($genre->color());
});

test('genre context returns session genre when guest', function () {
    $genre = Genre::where('name', 'guitarist')->firstOrFail();
    Session::put('current_genre_id', $genre->id);

    expect(GenreContext::getId())->toBe($genre->id);
    expect(GenreContext::get()->id)->toBe($genre->id);
    expect(GenreContext::hasGenre())->toBeTrue();
});

test('genre context prefers user genre over session', function () {
    $userGenre = Genre::where('name', 'organist')->firstOrFail();
    $sessionGenre = Genre::where('name', 'guitarist')->firstOrFail();
    $user = User::factory()->create(['current_genre_id' => $userGenre->id]);

    Session::put('current_genre_id', $sessionGenre->id);
    $this->actingAs($user);

    expect(GenreContext::getId())->toBe($userGenre->id);
    expect(GenreContext::get()->id)->toBe($userGenre->id);
});

test('genre context set updates user genre', function () {
    $genre = Genre::where('name', 'organist')->firstOrFail();
    $user = User::factory()->create(['current_genre_id' => null]);

    $this->actingAs($user);

    GenreContext::set($genre->id);

    expect($user->fresh()->current_genre_id)->toBe($genre->id);
    expect(Session::has('current_genre_id'))->toBeFalse();
});

test('genre context set updates session when guest', function () {
    $genre = Genre::where('name', 'guitarist')->firstOrFail();

    GenreContext::set($genre->id);

    expect(Session::get('current_genre_id'))->toBe($genre->id);
});

test('genre context clear removes genre', function () {
    $genre = Genre::where('name', 'organist')->firstOrFail();
    $user = User::factory()->create(['current_genre_id' => $genre->id]);

    $this->actingAs($user);

    GenreContext::clear();

    expect($user->fresh()->current_genre_id)->toBeNull();
    expect(GenreContext::getId())->toBeNull();
});

test('genre context clear removes session genre', function () {
    Session::put('current_genre_id', 5);

    GenreContext::clear();

    expect(Session::has('current_genre_id'))->toBeFalse();
});

test('genre context label returns default when no genre', function () {
    expect(GenreContext::label())->toBe(__('No genre selected'));
});

test('genre context icon and color return null when no genre', function () {
    expect(GenreContext::icon())->toBeNull();
    expect(GenreContext::color())->toBeNull();
});
