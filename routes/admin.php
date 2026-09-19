<?php

use App\Livewire\Pages\Admin\BulkImports;
use App\Livewire\Pages\Admin\DiatarCatalog;
use App\Livewire\Pages\Admin\DirektoriumEditions;
use App\Livewire\Pages\Admin\DirektoriumEntries;
use App\Livewire\Pages\Admin\MusicPlanSlots;
use App\Livewire\Pages\Admin\MusicPlanTemplates;
use App\Livewire\Pages\Admin\MusicPlanTemplateSlots;
use App\Livewire\Pages\Admin\RolePermissionManager;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::livewire('nickname-data', 'pages::admin.nickname-data')->name('admin.nickname-data');
    Route::livewire('users', 'pages::admin.users')->name('admin.users');
    Route::livewire('content-statistics', 'pages::admin.content-statistics')->name('admin.content-statistics');

    // Music Plan Templates
    Route::livewire('music-plan-slots', MusicPlanSlots::class)->name('admin.music-plan-slots');
    Route::livewire('music-plan-templates', MusicPlanTemplates::class)->name('admin.music-plan-templates');
    Route::livewire('music-plan-templates/{template}/slots', MusicPlanTemplateSlots::class)->name('admin.music-plan-template-slots');

    // Direktórium
    Route::livewire('direktorium', DirektoriumEditions::class)->name('admin.direktorium');
    Route::livewire('direktorium/entries', DirektoriumEntries::class)->name('admin.direktorium.entries');
    Route::livewire('diatar-catalog', DiatarCatalog::class)->name('admin.diatar-catalog');

    // Bulk Imports
    Route::livewire('bulk-imports', BulkImports::class)->name('admin.bulk-imports');

    // Role Permissions
    Route::livewire('role-permissions', RolePermissionManager::class)->name('admin.role-permissions');

    // URL Whitelist Management
    Route::livewire('url-whitelist', 'pages::admin.url-whitelist')->name('admin.url-whitelist');

});
