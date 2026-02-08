<?php

use App\Livewire\Pages\Admin\MasterData;
use App\Livewire\Pages\Admin\Users;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::livewire('master-data', MasterData::class)->name('admin.master-data');
    Route::livewire('users', Users::class)->name('admin.users');
});
