<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::livewire('nickname-data', 'pages::admin.nickname-data')->name('admin.nickname-data');
    Route::livewire('users', 'pages::admin.users')->name('admin.users');
});
