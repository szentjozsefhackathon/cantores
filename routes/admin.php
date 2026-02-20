<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::livewire('nickname-data', 'pages::admin.nickname-data')->name('admin.nickname-data');
    Route::livewire('users', 'pages::admin.users')->name('admin.users');

    // Music Plan Templates
    Route::livewire('music-plan-slots', \App\Livewire\Pages\Admin\MusicPlanSlots::class)->name('admin.music-plan-slots');
    Route::livewire('music-plan-templates', \App\Livewire\Pages\Admin\MusicPlanTemplates::class)->name('admin.music-plan-templates');
    Route::livewire('music-plan-templates/{template}/slots', \App\Livewire\Pages\Admin\MusicPlanTemplateSlots::class)->name('admin.music-plan-template-slots');

    // Bulk Imports
    Route::livewire('bulk-imports', \App\Livewire\Pages\Admin\BulkImports::class)->name('admin.bulk-imports');

    // Role Permissions
    Route::livewire('role-permissions', \App\Livewire\Pages\Admin\RolePermissionManager::class)->name('admin.role-permissions');

    // URL Whitelist Management
    Route::livewire('url-whitelist', \App\Livewire\Pages\Admin\UrlWhitelistManager::class)->name('admin.url-whitelist');

});
