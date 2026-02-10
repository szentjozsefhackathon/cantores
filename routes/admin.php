<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::livewire('nickname-data', 'pages::admin.nickname-data')->name('admin.nickname-data');
    Route::livewire('users', 'pages::admin.users')->name('admin.users');

    // Music Plan Templates
    Route::livewire('music-plan-slots', \App\Livewire\Pages\Admin\MusicPlanSlots::class)->name('admin.music-plan-slots');
    Route::livewire('music-plan-templates', \App\Livewire\Pages\Admin\MusicPlanTemplates::class)->name('admin.music-plan-templates');
    Route::livewire('music-plan-templates/{template}/slots', \App\Livewire\Pages\Admin\MusicPlanTemplateSlots::class)->name('admin.music-plan-template-slots');
});
