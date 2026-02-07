<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Pages\Admin\MasterData;

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::livewire('master-data', MasterData::class)->name('admin.master-data');
});
