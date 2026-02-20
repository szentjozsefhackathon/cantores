<div>
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">URL Whitelist Rules</h1>
            <flux:button wire:click="create" variant="primary">
                <flux:icon name="plus" class="mr-2 size-4" />
                New Rule
            </flux:button>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Manage URL whitelist rules for music URL validation. Rules define which URLs are allowed.
        </p>
    </div>

    @if (session()->has('message'))
        <flux:alert variant="success" class="mb-6">
            {{ session('message') }}
        </flux:alert>
    @endif

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search hostname, path, description..." />
        <flux:select wire:model.live="statusFilter">
            <option value="">All Status</option>
            <option value="active">Active Only</option>
            <option value="inactive">Inactive Only</option>
        </flux:select>
        <div class="flex items-center gap-2">
            <flux:button wire:click="bulkActivate" :disabled="empty($selectedRules)" variant="outline" size="sm">
                Activate
            </flux:button>
            <flux:button wire:click="bulkDeactivate" :disabled="empty($selectedRules)" variant="outline" size="sm">
                Deactivate
            </flux:button>
            <flux:button wire:click="bulkDelete" :disabled="empty($selectedRules)" variant="destructive" size="sm">
                Delete
            </flux:button>
        </div>
    </div>

    <flux:card>
        <flux:table :hover="true">
            <flux:table.head>
                <flux:table.head-cell class="w-12">
                    <flux:checkbox wire:model.live="selectedRules" value="all" />
                </flux:table.head-cell>
                <flux:table.head-cell>Hostname</flux:table.head-cell>
                <flux:table.head-cell>Path Prefix</flux:table.head-cell>
                <flux:table.head-cell>Scheme</flux:table.head-cell>
                <flux:table.head-cell>Status</flux:table.head-cell>
                <flux:table.head-cell>Description</flux:table.head-cell>
                <flux:table.head-cell>Matching URLs</flux:table.head-cell>
                <flux:table.head-cell>Actions</flux:table.head-cell>
            </flux:table.head>
            <flux:table.body>
                @forelse ($this->rules as $rule)
                    <flux:table.row wire:key="rule-{{ $rule->id }}">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selectedRules" value="{{ $rule->id }}" />
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $rule->hostname }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $rule->path_prefix ?: '/' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="outline">{{ strtoupper($rule->scheme) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :variant="$rule->is_active ? 'success' : 'secondary'">
                                {{ $rule->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-xs truncate">{{ $rule->description }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $this->matchingUrlsCount[$rule->id] ?? 0 }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button wire:click="edit({{ $rule->id }})" variant="ghost" size="sm">
                                    <flux:icon name="pencil" class="size-4" />
                                </flux:button>
                                <flux:button wire:click="confirmDelete({{ $rule->id }})" variant="ghost" size="sm" class="text-red-600">
                                    <flux:icon name="trash" class="size-4" />
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-8 text-center text-gray-500">
                            No whitelist rules found.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.body>
        </flux:table>
        <div class="mt-4">
            {{ $this->rules->links() }}
        </div>
    </flux:card>

    <!-- Create/Edit Modal -->
    <flux:modal wire:model="showCreateModal" size="lg">
        <flux:modal.header>
            <h2 class="text-lg font-semibold">{{ $editingId ? 'Edit Rule' : 'Create New Rule' }}</h2>
        </flux:modal.header>
        <flux:modal.body>
            <div class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="form.hostname" label="Hostname" required />
                    <flux:input wire:model="form.path_prefix" label="Path Prefix" placeholder="/" />
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model="form.scheme" label="Scheme">
                        <option value="http">HTTP</option>
                        <option value="https">HTTPS</option>
                    </flux:select>
                    <div class="space-y-4">
                        <flux:checkbox wire:model="form.allow_any_port" label="Allow any port" />
                        <flux:checkbox wire:model="form.is_active" label="Active" />
                    </div>
                </div>
                <flux:textarea wire:model="form.description" label="Description" rows="3" />
                @error('form.hostname')
                    <flux:alert variant="error">{{ $message }}</flux:alert>
                @enderror
                @error('form.path_prefix')
                    <flux:alert variant="error">{{ $message }}</flux:alert>
                @enderror
            </div>

            <!-- Test URL Section -->
            <div class="mt-6 border-t pt-6">
                <h3 class="mb-4 text-lg font-semibold">Test Rule</h3>
                <div class="flex gap-2">
                    <flux:input wire:model="testUrl" placeholder="https://example.com/path" class="flex-1" />
                    <flux:button wire:click="testRule" variant="outline">Test</flux:button>
                </div>
                @if (!empty($testResult))
                    <div class="mt-4">
                        <flux:alert :variant="$testResult['matches'] ? 'success' : 'error'">
                            {{ $testResult['message'] }}
                        </flux:alert>
                    </div>
                @endif
            </div>
        </flux:modal.body>
        <flux:modal.footer>
            <flux:button wire:click="save" variant="primary">Save Rule</flux:button>
            <flux:button wire:click="$set('showCreateModal', false)" variant="ghost">Cancel</flux:button>
        </flux:modal.footer>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model="showDeleteModal" size="sm">
        <flux:modal.header>
            <h2 class="text-lg font-semibold">Confirm Delete</h2>
        </flux:modal.header>
        <flux:modal.body>
            <p class="text-gray-700 dark:text-gray-300">
                Are you sure you want to delete this rule? This action cannot be undone.
            </p>
        </flux:modal.body>
        <flux:modal.footer>
            <flux:button wire:click="delete" variant="destructive">Delete</flux:button>
            <flux:button wire:click="$set('showDeleteModal', false)" variant="ghost">Cancel</flux:button>
        </flux:modal.footer>
    </flux:modal>
</div>