<?php

use App\Models\MusicVerification;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->nonAdmin = User::factory()->create(['email' => 'user@example.com']);
    $this->verification = MusicVerification::factory()->create();
});

test('admin can view any verification', function () {
    expect($this->admin->can('view', $this->verification))->toBeTrue();
});

test('non-admin can view verification they created', function () {
    $verification = MusicVerification::factory()->create(['verifier_id' => $this->nonAdmin->id]);

    expect($this->nonAdmin->can('view', $verification))->toBeTrue();
});

test('non-admin cannot view verification they did not create', function () {
    $otherUser = User::factory()->create();
    $verification = MusicVerification::factory()->create(['verifier_id' => $otherUser->id]);

    expect($this->nonAdmin->can('view', $verification))->toBeFalse();
});

test('admin can create verification', function () {
    expect($this->admin->can('create', MusicVerification::class))->toBeTrue();
});

test('non-admin cannot create verification', function () {
    expect($this->nonAdmin->can('create', MusicVerification::class))->toBeFalse();
});

test('admin can update verification', function () {
    expect($this->admin->can('update', $this->verification))->toBeTrue();
});

test('non-admin cannot update verification', function () {
    expect($this->nonAdmin->can('update', $this->verification))->toBeFalse();
});

test('admin can delete verification', function () {
    expect($this->admin->can('delete', $this->verification))->toBeTrue();
});

test('non-admin cannot delete verification', function () {
    expect($this->nonAdmin->can('delete', $this->verification))->toBeFalse();
});

test('admin can restore verification', function () {
    expect($this->admin->can('restore', $this->verification))->toBeTrue();
});

test('non-admin cannot restore verification', function () {
    expect($this->nonAdmin->can('restore', $this->verification))->toBeFalse();
});

test('admin can force delete verification', function () {
    expect($this->admin->can('forceDelete', $this->verification))->toBeTrue();
});

test('non-admin cannot force delete verification', function () {
    expect($this->nonAdmin->can('forceDelete', $this->verification))->toBeFalse();
});

test('admin can verify verification', function () {
    expect($this->admin->can('verify', $this->verification))->toBeTrue();
});

test('non-admin cannot verify verification', function () {
    expect($this->nonAdmin->can('verify', $this->verification))->toBeFalse();
});

test('admin can reject verification', function () {
    expect($this->admin->can('reject', $this->verification))->toBeTrue();
});

test('non-admin cannot reject verification', function () {
    expect($this->nonAdmin->can('reject', $this->verification))->toBeFalse();
});

test('viewAny returns true for authenticated user', function () {
    expect($this->nonAdmin->can('viewAny', MusicVerification::class))->toBeTrue();
});
