<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::livewire('master-data', 'pages::admin.master-data')->name('admin.master-data');
    Route::livewire('users', 'pages::admin.users')->name('admin.users');
});
