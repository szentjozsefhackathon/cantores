# MusicPlan Templates Design Document

## Overview
MusicPlan Templates provide a way to define standard structures for Mass music plans. Slots are independent entities that can be reused across templates. Templates collect slots with specific ordering and indicate whether each slot is automatically included.

## Database Schema

### Table: `music_plan_slots`
| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | bigint | Primary key | AUTO_INCREMENT |
| `name` | string | Slot name (e.g., "Entrance Procession", "Kyrie") | NOT NULL |
| `description` | text | Optional description of the slot | NULLABLE |
| `created_at` | timestamp | When the record was created | NULLABLE |
| `updated_at` | timestamp | When the record was last updated | NULLABLE |
| `deleted_at` | timestamp | Soft delete timestamp | NULLABLE |

**Indexes:**
1. Primary key: `id`
2. Name index: `name` for searching

### Table: `music_plan_templates`
| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | bigint | Primary key | AUTO_INCREMENT |
| `name` | string | Template name (e.g., "Mass", "Sunday Mass", "Wedding Mass") | NOT NULL |
| `description` | text | Optional description of the template | NULLABLE |
| `is_active` | boolean | Whether the template is active (can be used) | DEFAULT true |
| `created_at` | timestamp | When the record was created | NULLABLE |
| `updated_at` | timestamp | When the record was last updated | NULLABLE |
| `deleted_at` | timestamp | Soft delete timestamp | NULLABLE |

**Indexes:**
1. Primary key: `id`
2. Active templates index: `is_active` for filtering
3. Name index: `name` for searching

### Table: `music_plan_template_slots` (Pivot table with ordering and inclusion)
| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | bigint | Primary key | AUTO_INCREMENT |
| `template_id` | bigint | Foreign key to music_plan_templates | NOT NULL |
| `slot_id` | bigint | Foreign key to music_plan_slots | NOT NULL |
| `sequence` | integer | Order of the slot within the template | NOT NULL |
| `is_included_by_default` | boolean | Whether this slot is automatically included in music plans created from this template | DEFAULT true |
| `created_at` | timestamp | When the record was created | NULLABLE |
| `updated_at` | timestamp | When the record was last updated | NULLABLE |

**Indexes:**
1. Primary key: `id`
2. Foreign key indexes: `template_id`, `slot_id`
3. Template ordering index: `(template_id, sequence)` for efficient ordering
4. Unique constraint: `(template_id, slot_id)` to prevent duplicate slots in same template
5. Inclusion index: `is_included_by_default` for filtering

**Foreign Keys:**
- `music_plan_template_slots.template_id` → `music_plan_templates.id` (cascade on delete)
- `music_plan_template_slots.slot_id` → `music_plan_slots.id` (restrict on delete)

## Eloquent Model Design

### Model: `MusicPlanSlot`
Located at `app/Models/MusicPlanSlot.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MusicPlanSlot extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the templates that include this slot.
     */
    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(MusicPlanTemplate::class, 'music_plan_template_slots')
            ->withPivot(['sequence', 'is_included_by_default'])
            ->orderByPivot('sequence');
    }

    /**
     * Scope for active slots (not soft deleted).
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }
}
```

### Model: `MusicPlanTemplate`
Located at `app/Models/MusicPlanTemplate.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MusicPlanTemplate extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the slots for this template with ordering and inclusion info.
     */
    public function slots(): BelongsToMany
    {
        return $this->belongsToMany(MusicPlanSlot::class, 'music_plan_template_slots')
            ->withPivot(['sequence', 'is_included_by_default'])
            ->orderByPivot('sequence');
    }

    /**
     * Get only slots included by default.
     */
    public function defaultSlots()
    {
        return $this->slots()->wherePivot('is_included_by_default', true);
    }

    /**
     * Get only advanced (optional) slots.
     */
    public function advancedSlots()
    {
        return $this->slots()->wherePivot('is_included_by_default', false);
    }

    /**
     * Scope for active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for including slots.
     */
    public function scopeWithSlots($query)
    {
        return $query->with('slots');
    }

    /**
     * Attach a slot to the template with sequence and inclusion flag.
     */
    public function attachSlot(MusicPlanSlot $slot, int $sequence, bool $isIncludedByDefault = true): void
    {
        $this->slots()->attach($slot->id, [
            'sequence' => $sequence,
            'is_included_by_default' => $isIncludedByDefault,
        ]);
    }

    /**
     * Update a slot's sequence and inclusion flag.
     */
    public function updateSlot(MusicPlanSlot $slot, int $sequence, bool $isIncludedByDefault): void
    {
        $this->slots()->updateExistingPivot($slot->id, [
            'sequence' => $sequence,
            'is_included_by_default' => $isIncludedByDefault,
        ]);
    }

    /**
     * Detach a slot from the template.
     */
    public function detachSlot(MusicPlanSlot $slot): void
    {
        $this->slots()->detach($slot->id);
    }
}
```

### Pivot Model: `MusicPlanTemplateSlot`
Located at `app/Models/MusicPlanTemplateSlot.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MusicPlanTemplateSlot extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'music_plan_template_slots';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'slot_id',
        'sequence',
        'is_included_by_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'is_included_by_default' => 'boolean',
        ];
    }
}
```

## Future Integration with MusicPlan

### Table: `music_plan_slot_assignments` (for actual MusicPlans)
In the future, we'll need to create a table to connect MusicPlans to Slots:
- `music_plan_id` foreign key to `music_plans`
- `slot_id` foreign key to `music_plan_slots`
- `music_song_id` (or similar) for the actual music assignment
- `notes` for additional information
- `sequence` for ordering within the plan

### Model Modification for `MusicPlan`
We'll add:
```php
public function slots(): BelongsToMany
{
    return $this->belongsToMany(MusicPlanSlot::class, 'music_plan_slot_assignments')
        ->withPivot(['music_song_id', 'notes', 'sequence'])
        ->orderByPivot('sequence');
}

public function template(): BelongsTo
{
    return $this->belongsTo(MusicPlanTemplate::class);
}
```

## Admin Interface Requirements

### Slot Management (Global)
1. List all slots with pagination
2. Create new slot (name, description)
3. Edit existing slot
4. Soft delete slot
5. View which templates use each slot

### Template Management
1. List all templates with pagination
2. Create new template (name, description, is_active)
3. Edit existing template
4. Soft delete/inactivate template
5. Manage template slots (add/remove/reorder/set inclusion)

### Template Slot Management (within template edit)
1. Add existing slot to template (with sequence and is_included_by_default flag)
2. Remove slot from template
3. Reorder slots via drag-and-drop or sequence numbers
4. Toggle inclusion flag for each slot
5. Create new slot directly from template interface

### UI Components
- Use Flux UI components for consistent styling
- Modal forms for create/edit operations
- Confirmation dialogs for deletions
- Sortable table for template slots
- Search/select for adding existing slots to templates
- Toggle switches for inclusion flags

## Routes
```
# Slot Management
GET    /admin/music-plan-slots          - List slots
GET    /admin/music-plan-slots/create   - Create slot form
POST   /admin/music-plan-slots          - Store slot
GET    /admin/music-plan-slots/{id}/edit - Edit slot form
PUT    /admin/music-plan-slots/{id}     - Update slot
DELETE /admin/music-plan-slots/{id}     - Soft delete slot

# Template Management
GET    /admin/music-plan-templates          - List templates
GET    /admin/music-plan-templates/create   - Create template form
POST   /admin/music-plan-templates          - Store template
GET    /admin/music-plan-templates/{id}/edit - Edit template form
PUT    /admin/music-plan-templates/{id}     - Update template
DELETE /admin/music-plan-templates/{id}     - Soft delete template

# Template Slot Management
POST   /admin/music-plan-templates/{id}/slots - Add slot to template
PUT    /admin/music-plan-templates/{id}/slots/{slotId} - Update slot (sequence & inclusion)
DELETE /admin/music-plan-templates/{id}/slots/{slotId} - Remove slot from template
```

## Form Request Classes

### StoreMusicPlanSlotRequest
Located at `app/Http/Requests/StoreMusicPlanSlotRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMusicPlanSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('music_plan_slots', 'name')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => __('A slot name is required.'),
            'name.unique' => __('A slot with this name already exists.'),
            'description.max' => __('Description cannot exceed 1000 characters.'),
        ];
    }
}
```

### UpdateMusicPlanSlotRequest
Located at `app/Http/Requests/UpdateMusicPlanSlotRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMusicPlanSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('music_plan_slots', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('music_plan_slot')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => __('A slot name is required.'),
            'name.unique' => __('A slot with this name already exists.'),
            'description.max' => __('Description cannot exceed 1000 characters.'),
        ];
    }
}
```

### StoreMusicPlanTemplateRequest
Located at `app/Http/Requests/StoreMusicPlanTemplateRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMusicPlanTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('music_plan_templates', 'name')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => __('A template name is required.'),
            'name.unique' => __('A template with this name already exists.'),
            'description.max' => __('Description cannot exceed 1000 characters.'),
        ];
    }
}
```

### UpdateMusicPlanTemplateRequest
Located at `app/Http/Requests/UpdateMusicPlanTemplateRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMusicPlanTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('music_plan_templates', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('music_plan_template')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => __('A template name is required.'),
            'name.unique' => __('A template with this name already exists.'),
            'description.max' => __('Description cannot exceed 1000 characters.'),
        ];
    }
}
```

### StoreMusicPlanTemplateSlotRequest
Located at `app/Http/Requests/StoreMusicPlanTemplateSlotRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMusicPlanTemplateSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slot_id' => ['required', 'exists:music_plan_slots,id'],
            'sequence' => ['required', 'integer', 'min:1'],
            'is_included_by_default' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'slot_id.required' => __('A slot must be selected.'),
            'slot_id.exists' => __('The selected slot does not exist.'),
            'sequence.required' => __('A sequence number is required.'),
            'sequence.min' => __('Sequence must be at least 1.'),
        ];
    }
}
```

### UpdateMusicPlanTemplateSlotRequest
Located at `app/Http/Requests/UpdateMusicPlanTemplateSlotRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMusicPlanTemplateSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sequence' => ['required', 'integer', 'min:1'],
            'is_included_by_default' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'sequence.required' => __('A sequence number is required.'),
            'sequence.min' => __('Sequence must be at least 1.'),
        ];
    }
}
```

## Livewire Components Design

### MusicPlanSlots Component
Located at `app/Livewire/Pages/Admin/MusicPlanSlots.php`

```php
<?php

namespace App\Livewire\Pages\Admin;

use App\Http\Requests\StoreMusicPlanSlotRequest;
use App\Http\Requests\UpdateMusicPlanSlotRequest;
use App\Models\MusicPlanSlot;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class MusicPlanSlots extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?MusicPlanSlot $editingSlot = null;

    // Form fields
    public string $name = '';
    public string $description = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', MusicPlanSlot::class);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $slots = MusicPlanSlot::active()
            ->when($this->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.pages.admin.music-plan-slots', [
            'slots' => $slots,
        ]);
    }

    /**
     * Show the create modal.
     */
    public function showCreate(): void
    {
        $this->authorize('create', MusicPlanSlot::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /**
     * Show the edit modal.
     */
    public function showEdit(MusicPlanSlot $slot): void
    {
        $this->authorize('update', $slot);
        $this->editingSlot = $slot;
        $this->name = $slot->name;
        $this->description = $slot->description ?? '';
        $this->showEditModal = true;
    }

    /**
     * Create a new slot.
     */
    public function create(): void
    {
        $this->authorize('create', MusicPlanSlot::class);
        
        $validated = $this->validate((new StoreMusicPlanSlotRequest)->rules());
        
        MusicPlanSlot::create($validated);
        
        $this->showCreateModal = false;
        $this->resetForm();
        $this->dispatch('slot-created');
    }

    /**
     * Update an existing slot.
     */
    public function update(): void
    {
        $this->authorize('update', $this->editingSlot);
        
        $validated = $this->validate((new UpdateMusicPlanSlotRequest)->rules());
        
        $this->editingSlot->update($validated);
        
        $this->showEditModal = false;
        $this->resetForm();
        $this->dispatch('slot-updated');
    }

    /**
     * Soft delete a slot.
     */
    public function delete(MusicPlanSlot $slot): void
    {
        $this->authorize('delete', $slot);
        
        $slot->delete();
        
        $this->dispatch('slot-deleted');
    }

    /**
     * Reset form fields.
     */
    private function resetForm(): void
    {
        $this->reset(['name', 'description']);
        $this->editingSlot = null;
        $this->resetErrorBag();
    }
}
```

### MusicPlanTemplates Component
Located at `app/Livewire/Pages/Admin/MusicPlanTemplates.php`

```php
<?php

namespace App\Livewire\Pages\Admin;

use App\Http\Requests\StoreMusicPlanTemplateRequest;
use App\Http\Requests\UpdateMusicPlanTemplateRequest;
use App\Models\MusicPlanTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class MusicPlanTemplates extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?MusicPlanTemplate $editingTemplate = null;

    // Form fields
    public string $name = '';
    public string $description = '';
    public bool $is_active = true;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', MusicPlanTemplate::class);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $templates = MusicPlanTemplate::active()
            ->when($this->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.pages.admin.music-plan-templates', [
            'templates' => $templates,
        ]);
    }

    /**
     * Show the create modal.
     */
    public function showCreate(): void
    {
        $this->authorize('create', MusicPlanTemplate::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /**
     * Show the edit modal.
     */
    public function showEdit(MusicPlanTemplate $template): void
    {
        $this->authorize('update', $template);
        $this->editingTemplate = $template;
        $this->name = $template->name;
        $this->description = $template->description ?? '';
        $this->is_active = $template->is_active;
        $this->showEditModal = true;
    }

    /**
     * Create a new template.
     */
    public function create(): void
    {
        $this->authorize('create', MusicPlanTemplate::class);
        
        $validated = $this->validate((new StoreMusicPlanTemplateRequest)->rules());
        
        MusicPlanTemplate::create($validated);
        
        $this->showCreateModal = false;
        $this->resetForm();
        $this->dispatch('template-created');
    }

    /**
     * Update an existing template.
     */
    public function update(): void
    {
        $this->authorize('update', $this->editingTemplate);
        
        $validated = $this->validate((new UpdateMusicPlanTemplateRequest)->rules());
        
        $this->editingTemplate->update($validated);
        
        $this->showEditModal = false;
        $this->resetForm();
        $this->dispatch('template-updated');
    }

    /**
     * Soft delete a template.
     */
    public function delete(MusicPlanTemplate $template): void
    {
        $this->authorize('delete', $template);
        
        $template->delete();
        
        $this->dispatch('template-deleted');
    }

    /**
     * Toggle template active status.
     */
    public function toggleActive(MusicPlanTemplate $template): void
    {
        $this->authorize('update', $template);
        
        $template->update(['is_active' => !$template->is_active]);
        
        $this->dispatch('template-status-updated');
    }

    /**
     * Reset form fields.
     */
    private function resetForm(): void
    {
        $this->reset(['name', 'description', 'is_active']);
        $this->editingTemplate = null;
        $this->resetErrorBag();
    }
}
```

### MusicPlanTemplateSlots Component
Located at `app/Livewire/Pages/Admin/MusicPlanTemplateSlots.php`

```php
<?php

namespace App\Livewire\Pages\Admin;

use App\Http\Requests\StoreMusicPlanTemplateSlotRequest;
use App\Http\Requests\UpdateMusicPlanTemplateSlotRequest;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class MusicPlanTemplateSlots extends Component
{
    use AuthorizesRequests;

    public MusicPlanTemplate $template;
    public bool $showAddSlotModal = false;
    public bool $showEditSlotModal = false;
    public ?array $editingSlotPivot = null;

    // Add slot form fields
    public ?int $slot_id = null;
    public int $sequence = 1;
    public bool $is_included_by_default = true;

    // Edit slot form fields
    public int $edit_sequence = 1;
    public bool $edit_is_included_by_default = true;

    /**
     * Mount the component.
     */
    public function mount(MusicPlanTemplate $template): void
    {
        $this->template = $template->load('slots');
        $this->authorize('update', $template);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $availableSlots = MusicPlanSlot::active()
            ->whereNotIn('id', $this->template->slots->pluck('id'))
            ->orderBy('name')
            ->get();

        return view('livewire.pages.admin.music-plan-template-slots', [
            'template' => $this->template,
            'availableSlots' => $availableSlots,
        ]);
    }

    /**
     * Show the add slot modal.
     */
    public function showAddSlot(): void
    {
        $this->authorize('update', $this->template);
        $this->resetAddSlotForm();
        $this->showAddSlotModal = true;
    }

    /**
     * Show the edit slot modal.
     */
    public function showEditSlot(array $slotPivot): void
    {
        $this->authorize('update', $this->template);
        $this->editingSlotPivot = $slotPivot;
        $this->edit_sequence = $slotPivot['pivot']['sequence'];
        $this->edit_is_included_by_default = $slotPivot['pivot']['is_included_by_default'];
        $this->showEditSlotModal = true;
    }

    /**
     * Add a slot to the template.
     */
    public function addSlot(): void
    {
        $this->authorize('update', $this->template);
        
        $validated = $this->validate((new StoreMusicPlanTemplateSlotRequest)->rules());
        
        $this->template->attachSlot(
            MusicPlanSlot::findOrFail($validated['slot_id']),
            $validated['sequence'],
            $validated['is_included_by_default']
        );
        
        $this->showAddSlotModal = false;
        $this->resetAddSlotForm();
        $this->dispatch('slot-added');
    }

    /**
     * Update a slot in the template.
     */
    public function updateSlot(): void
    {
        $this->authorize('update', $this->template);
        
        $validated = $this->validate([
            'edit_sequence' => ['required', 'integer', 'min:1'],
            'edit_is_included_by_default' => ['boolean'],
        ]);
        
        $slot = MusicPlanSlot::findOrFail($this->editingSlotPivot['id']);
        
        $this->template->updateSlot(
            $slot,
            $validated['edit_sequence'],
            $validated['edit_is_included_by_default']
        );
        
        $this->showEditSlotModal = false;
        $this->resetEditSlotForm();
        $this->dispatch('slot-updated');
    }

    /**
     * Remove a slot from the template.
     */
    public function removeSlot(MusicPlanSlot $slot): void
    {
        $this->authorize('update', $this->template);
        
        $this->template->detachSlot($slot);
        
        $this->dispatch('slot-removed');
    }

    /**
     * Reset add slot form fields.
     */
    private function resetAddSlotForm(): void
    {
        $this->reset(['slot_id', 'sequence', 'is_included_by_default']);
        $this->resetErrorBag();
    }

    /**
     * Reset edit slot form fields.
     */
    private function resetEditSlotForm(): void
    {
        $this->reset(['editingSlotPivot', 'edit_sequence', 'edit_is_included_by_default']);
        $this->resetErrorBag();
    }
}
```

## Routes and Navigation Design

### Updated Admin Routes (`routes/admin.php`)
```php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::livewire('nickname-data', 'pages::admin.nickname-data')->name('admin.nickname-data');
    Route::livewire('users', 'pages::admin.users')->name('admin.users');
    
    // Music Plan Templates Management
    Route::livewire('music-plan-slots', 'pages::admin.music-plan-slots')->name('admin.music-plan-slots');
    Route::livewire('music-plan-templates', 'pages::admin.music-plan-templates')->name('admin.music-plan-templates');
    Route::livewire('music-plan-templates/{template}/slots', 'pages::admin.music-plan-template-slots')
        ->name('admin.music-plan-template-slots');
});
```

### Updated Admin Navigation (`resources/views/pages/admin/layout.blade.php`)
```blade
<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Admin') }}">
            <flux:navlist.item :href="route('admin.nickname-data')" wire:navigate :current="request()->routeIs('admin.nickname-data')">
                {{ __('Nickname and city master data') }}
            </flux:navlist.item>
            <flux:navlist.item :href="route('admin.users')" wire:navigate :current="request()->routeIs('admin.users')">
                {{ __('Users') }}
            </flux:navlist.item>
            
            <flux:navlist.separator />
            
            <flux:navlist.group :label="__('Music Plan Templates')">
                <flux:navlist.item :href="route('admin.music-plan-slots')" wire:navigate :current="request()->routeIs('admin.music-plan-slots')">
                    {{ __('Slots') }}
                </flux:navlist.item>
                <flux:navlist.item :href="route('admin.music-plan-templates')" wire:navigate :current="request()->routeIs('admin.music-plan-templates')">
                    {{ __('Templates') }}
                </flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>
        <div class="mt-5 w-full">
            {{ $slot }}
            
        </div>
    </div>
</div>
```

### Route Summary
| Method | Route | Component | Name | Description |
|--------|-------|-----------|------|-------------|
| GET | `/admin/music-plan-slots` | `MusicPlanSlots` | `admin.music-plan-slots` | List all music plan slots |
| GET | `/admin/music-plan-templates` | `MusicPlanTemplates` | `admin.music-plan-templates` | List all music plan templates |
| GET | `/admin/music-plan-templates/{template}/slots` | `MusicPlanTemplateSlots` | `admin.music-plan-template-slots` | Manage slots for a specific template |

## UI Design

### Template Management UI

#### Template List Page (`resources/views/livewire/pages/admin/music-plan-templates.blade.php`)
```blade
<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading>{{ __('Music Plan Templates') }}</flux:heading>
            <flux:subheading>{{ __('Manage templates for music plans') }}</flux:subheading>
        </div>
        <flux:button variant="primary" wire:click="showCreate">
            {{ __('New Template') }}
        </flux:button>
    </div>

    <div class="mb-6">
        <flux:input
            wire:model.live="search"
            :placeholder="__('Search templates...')"
            icon="search"
        />
    </div>

    <flux:card>
        <flux:table>
            <flux:table.head>
                <flux:table.head-cell>{{ __('Name') }}</flux:table.head-cell>
                <flux:table.head-cell>{{ __('Description') }}</flux:table.head-cell>
                <flux:table.head-cell>{{ __('Status') }}</flux:table.head-cell>
                <flux:table.head-cell>{{ __('Slots') }}</flux:table.head-cell>
                <flux:table.head-cell>{{ __('Actions') }}</flux:table.head-cell>
            </flux:table.head>
            <flux:table.body>
                @forelse ($templates as $template)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium">{{ $template->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-gray-600 line-clamp-2">{{ $template->description }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :variant="$template->is_active ? 'success' : 'neutral'">
                                {{ $template->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <span>{{ $template->slots_count ?? $template->slots->count() }}</span>
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    :href="route('admin.music-plan-template-slots', $template)"
                                    wire:navigate
                                >
                                    {{ __('Manage') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="showEdit({{ $template->id }})"
                                >
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="toggleActive({{ $template->id }})"
                                >
                                    {{ $template->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:button>
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="delete({{ $template->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this template?') }}"
                                >
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-8 text-gray-500">
                            {{ __('No templates found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.body>
        </flux:table>
        
        <div class="mt-4">
            {{ $templates->links() }}
        </div>
    </flux:card>

    <!-- Create Template Modal -->
    <flux:modal wire:model="showCreateModal" :title="__('Create Template')">
        <form wire:submit="create">
            <div class="space-y-4">
                <flux:input
                    wire:model="name"
                    :label="__('Name')"
                    :placeholder="__('e.g., Sunday Mass, Wedding Mass')"
                    required
                />
                
                <flux:textarea
                    wire:model="description"
                    :label="__('Description')"
                    :placeholder="__('Optional description of the template')"
                    rows="3"
                />
                
                <flux:checkbox
                    wire:model="is_active"
                    :label="__('Active')"
                    :description="__('Template will be available for use')"
                />
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Create Template') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Template Modal -->
    <flux:modal wire:model="showEditModal" :title="__('Edit Template')">
        <form wire:submit="update">
            <div class="space-y-4">
                <flux:input
                    wire:model="name"
                    :label="__('Name')"
                    :placeholder="__('e.g., Sunday Mass, Wedding Mass')"
                    required
                />
                
                <flux:textarea
                    wire:model="description"
                    :label="__('Description')"
                    :placeholder="__('Optional description of the template')"
                    rows="3"
                />
                
                <flux:checkbox
                    wire:model="is_active"
                    :label="__('Active')"
                    :description="__('Template will be available for use')"
                />
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Update Template') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
```

#### Slot Management UI (within Template)

#### Slot List Page (`resources/views/livewire/pages/admin/music-plan-template-slots.blade.php`)
```blade
<div>
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading>{{ $template->name }}</flux:heading>
                <flux:subheading>{{ __('Manage slots for this template') }}</flux:subheading>
            </div>
            <div class="flex items-center gap-3">
                <flux:button variant="ghost" :href="route('admin.music-plan-templates')" wire:navigate>
                    {{ __('Back to Templates') }}
                </flux:button>
                <flux:button variant="primary" wire:click="showAddSlot">
                    {{ __('Add Slot') }}
                </flux:button>
            </div>
        </div>
        
        @if($template->description)
            <div class="mt-2 text-gray-600">
                {{ $template->description }}
            </div>
        @endif
    </div>

    <flux:card>
        <flux:table>
            <flux:table.head>
                <flux:table.head-cell class="w-16">{{ __('Order') }}</flux:table.head-cell>
                <flux:table.head-cell>{{ __('Slot Name') }}</flux:table.head-cell>
                <flux:table.head-cell>{{ __('Description') }}</flux:table.head-cell>
                <flux:table.head-cell>{{ __('Included by Default') }}</flux:table.head-cell>
                <flux:table.head-cell>{{ __('Actions') }}</flux:table.head-cell>
            </flux:table.head>
            <flux:table.body>
                @forelse ($template->slots as $slot)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-mono">{{ $slot->pivot->sequence }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $slot->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-gray-600 line-clamp-2">{{ $slot->description }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($slot->pivot->is_included_by_default)
                                <flux:badge variant="success">{{ __('Yes') }}</flux:badge>
                            @else
                                <flux:badge variant="neutral">{{ __('No (Advanced)') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="showEditSlot({{ json_encode($slot) }})"
                                >
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="removeSlot({{ $slot->id }})"
                                    wire:confirm="{{ __('Are you sure you want to remove this slot from the template?') }}"
                                >
                                    {{ __('Remove') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-8 text-gray-500">
                            {{ __('No slots added to this template yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.body>
        </flux:table>
    </flux:card>

    <!-- Add Slot Modal -->
    <flux:modal wire:model="showAddSlotModal" :title="__('Add Slot to Template')">
        <form wire:submit="addSlot">
            <div class="space-y-4">
                <flux:select
                    wire:model="slot_id"
                    :label="__('Slot')"
                    :placeholder="__('Select a slot')"
                    required
                >
                    <option value="">{{ __('Select a slot') }}</option>
                    @foreach($availableSlots as $slot)
                        <option value="{{ $slot->id }}">{{ $slot->name }}</option>
                    @endforeach
                </flux:select>
                
                <flux:input
                    wire:model="sequence"
                    type="number"
                    :label="__('Sequence')"
                    :placeholder="__('Order in template')"
                    min="1"
                    required
                />
                
                <flux:checkbox
                    wire:model="is_included_by_default"
                    :label="__('Include by Default')"
                    :description="__('This slot will be automatically included in music plans created from this template')"
                />
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showAddSlotModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Add Slot') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Slot Modal -->
    <flux:modal wire:model="showEditSlotModal" :title="__('Edit Slot in Template')">
        <form wire:submit="updateSlot">
            <div class="space-y-4">
                <div class="rounded-lg bg-gray-50 p-4">
                    <div class="font-medium">{{ $editingSlotPivot['name'] ?? '' }}</div>
                    @if($editingSlotPivot['description'] ?? null)
                        <div class="mt-1 text-sm text-gray-600">{{ $editingSlotPivot['description'] }}</div>
                    @endif
                </div>
                
                <flux:input
                    wire:model="edit_sequence"
                    type="number"
                    :label="__('Sequence')"
                    :placeholder="__('Order in template')"
                    min="1"
                    required
                />
                
                <flux:checkbox
                    wire:model="edit_is_included_by_default"
                    :label="__('Include by Default')"
                    :description="__('This slot will be automatically included in music plans created from this template')"
                />
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showEditSlotModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Update Slot') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
```

### Slot Management UI (Global)

#### Slot List Page (`resources/views/livewire/pages/admin/music-plan-slots.blade.php`)
```blade
<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading>{{ __('Music Plan Slots') }}</flux:heading>
            <flux:subheading>{{ __('Manage global slots for music plans') }}</flux:subheading>
        </div>
        <flux:button variant="primary" wire:click="showCreate">
            {{ __('New Slot') }}
        </flux:button>
    </div>

    <div class="mb-6">
        <flux:input
            wire:model.live="search"
            :placeholder="__('Search slots...')"
            icon="search"
        />
    </div>

    <flux:card>
        <flux:table>
            <flux:table.head>
                <flux:table.head-cell>{{ __('Name') }}</flux:table.head-cell>
                <flux:table.head-cell>{{ __('Description') }}</flux:table.head-cell>
                <flux:table.head-cell>{{ __('Used in Templates') }}</flux:table.head-cell>
                <flux:table.head-cell>{{ __('Actions') }}</flux:table.head-cell>
            </flux:table.head>
            <flux:table.body>
                @forelse ($slots as $slot)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium">{{ $slot->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-gray-600 line-clamp-2">{{ $slot->description }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-gray-600">
                                {{ $slot->templates_count ?? $slot->templates->count() }} {{ __('templates') }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="showEdit({{ $slot->id }})"
                                >
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="delete({{ $slot->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this slot?') }}"
                                >
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-8 text-gray-500">
                            {{ __('No slots found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.body>
        </flux:table>
        
        <div class="mt-4">
            {{ $slots->links() }}
        </div>
    </flux:card>

    <!-- Create Slot Modal -->
    <flux:modal wire:model="showCreateModal" :title="__('Create Slot')">
        <form wire:submit="create">
            <div class="space-y-4">
                <flux:input
                    wire:model="name"
                    :label="__('Name')"
                    :placeholder="__('e.g., Entrance Procession, Kyrie, Gloria')"
                    required
                />
                
                <flux:textarea
                    wire:model="description"
                    :label="__('Description')"
                    :placeholder="__('Optional description of the slot')"
                    rows="3"
                />
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Create Slot') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Slot Modal -->
    <flux:modal wire:model="showEditModal" :title="__('Edit Slot')">
        <form wire:submit="update">
            <div class="space-y-4">
                <flux:input
                    wire:model="name"
                    :label="__('Name')"
                    :placeholder="__('e.g., Entrance Procession, Kyrie, Gloria')"
                    required
                />
                
                <flux:textarea
                    wire:model="description"
                    :label="__('Description')"
                    :placeholder="__('Optional description of the slot')"
                    rows="3"
                />
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Update Slot') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
```

## Test Design

### Test Files Structure
```
tests/Feature/MusicPlanTemplates/
├── MusicPlanSlotTest.php
├── MusicPlanTemplateTest.php
└── MusicPlanTemplateSlotTest.php
```

### MusicPlanSlotTest
```php
<?php

use App\Models\MusicPlanSlot;
use App\Models\User;

test('guests cannot view slots', function () {
    $this->get(route('admin.music-plan-slots'))
        ->assertRedirect(route('login'));
});

test('non-admin users cannot view slots', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->get(route('admin.music-plan-slots'))
        ->assertForbidden();
});

test('admin can view slots', function () {
    $admin = User::factory()->admin()->create();
    MusicPlanSlot::factory()->count(3)->create();
    
    $this->actingAs($admin)
        ->get(route('admin.music-plan-slots'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.music-plan-slots');
});

test('admin can create a slot', function () {
    $admin = User::factory()->admin()->create();
    
    $this->actingAs($admin)
        ->livewire('pages::admin.music-plan-slots')
        ->set('name', 'Entrance Procession')
        ->set('description', 'Music for the entrance procession')
        ->call('create')
        ->assertDispatched('slot-created');
    
    $this->assertDatabaseHas('music_plan_slots', [
        'name' => 'Entrance Procession',
        'description' => 'Music for the entrance procession',
    ]);
});

test('admin can update a slot', function () {
    $admin = User::factory()->admin()->create();
    $slot = MusicPlanSlot::factory()->create(['name' => 'Old Name']);
    
    $this->actingAs($admin)
        ->livewire('pages::admin.music-plan-slots')
        ->call('showEdit', $slot->id)
        ->set('name', 'Updated Name')
        ->set('description', 'Updated Description')
        ->call('update')
        ->assertDispatched('slot-updated');
    
    $this->assertDatabaseHas('music_plan_slots', [
        'id' => $slot->id,
        'name' => 'Updated Name',
        'description' => 'Updated Description',
    ]);
});

test('admin can soft delete a slot', function () {
    $admin = User::factory()->admin()->create();
    $slot = MusicPlanSlot::factory()->create();
    
    $this->actingAs($admin)
        ->livewire('pages::admin.music-plan-slots')
        ->call('delete', $slot->id)
        ->assertDispatched('slot-deleted');
    
    $this->assertSoftDeleted($slot);
});

test('slot name must be unique', function () {
    $admin = User::factory()->admin()->create();
    MusicPlanSlot::factory()->create(['name' => 'Existing Slot']);
    
    $this->actingAs($admin)
        ->livewire('pages::admin.music-plan-slots')
        ->set('name', 'Existing Slot')
        ->call('create')
        ->assertHasErrors(['name' => 'unique']);
});
```

### MusicPlanTemplateTest
```php
<?php

use App\Models\MusicPlanTemplate;
use App\Models\User;

test('admin can view templates', function () {
    $admin = User::factory()->admin()->create();
    MusicPlanTemplate::factory()->count(3)->create();
    
    $this->actingAs($admin)
        ->get(route('admin.music-plan-templates'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.music-plan-templates');
});

test('admin can create a template', function () {
    $admin = User::factory()->admin()->create();
    
    $this->actingAs($admin)
        ->livewire('pages::admin.music-plan-templates')
        ->set('name', 'Sunday Mass')
        ->set('description', 'Template for Sunday Mass')
        ->set('is_active', true)
        ->call('create')
        ->assertDispatched('template-created');
    
    $this->assertDatabaseHas('music_plan_templates', [
        'name' => 'Sunday Mass',
        'description' => 'Template for Sunday Mass',
        'is_active' => true,
    ]);
});

test('admin can toggle template active status', function () {
    $admin = User::factory()->admin()->create();
    $template = MusicPlanTemplate::factory()->create(['is_active' => true]);
    
    $this->actingAs($admin)
        ->livewire('pages::admin.music-plan-templates')
        ->call('toggleActive', $template->id)
        ->assertDispatched('template-status-updated');
    
    $this->assertDatabaseHas('music_plan_templates', [
        'id' => $template->id,
        'is_active' => false,
    ]);
});

test('admin can soft delete a template', function () {
    $admin = User::factory()->admin()->create();
    $template = MusicPlanTemplate::factory()->create();
    
    $this->actingAs($admin)
        ->livewire('pages::admin.music-plan-templates')
        ->call('delete', $template->id)
        ->assertDispatched('template-deleted');
    
    $this->assertSoftDeleted($template);
});

test('template with slots cannot be hard deleted', function () {
    $admin = User::factory()->admin()->create();
    $template = MusicPlanTemplate::factory()->hasSlots(2)->create();
    
    // Attempt to force delete (should fail due to foreign key constraint)
    $this->expectException(\Illuminate\Database\QueryException::class);
    $template->forceDelete();
});
```

### MusicPlanTemplateSlotTest
```php
<?php

use App\Models\MusicPlanSlot;
use App\Models\MusicPlanTemplate;
use App\Models\User;

test('admin can view template slots', function () {
    $admin = User::factory()->admin()->create();
    $template = MusicPlanTemplate::factory()->create();
    
    $this->actingAs($admin)
        ->get(route('admin.music-plan-template-slots', $template))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.music-plan-template-slots');
});

test('admin can add slot to template', function () {
    $admin = User::factory()->admin()->create();
    $template = MusicPlanTemplate::factory()->create();
    $slot = MusicPlanSlot::factory()->create();
    
    $this->actingAs($admin)
        ->livewire('pages::admin.music-plan-template-slots', ['template' => $template])
        ->set('slot_id', $slot->id)
        ->set('sequence', 1)
        ->set('is_included_by_default', true)
        ->call('addSlot')
        ->assertDispatched('slot-added');
    
    $this->assertDatabaseHas('music_plan_template_slots', [
        'template_id' => $template->id,
        'slot_id' => $slot->id,
        'sequence' => 1,
        'is_included_by_default' => true,
    ]);
});

test('admin can update slot in template', function () {
    $admin = User::factory()->admin()->create();
    $template = MusicPlanTemplate::factory()->create();
    $slot = MusicPlanSlot::factory()->create();
    
    $template->slots()->attach($slot->id, [
        'sequence' => 1,
        'is_included_by_default' => true,
    ]);
    
    $this->actingAs($admin)
        ->livewire('pages::admin.music-plan-template-slots', ['template' => $template])
        ->call('showEditSlot', $template->slots->first()->toArray())
        ->set('edit_sequence', 2)
        ->set('edit_is_included_by_default', false)
        ->call('updateSlot')
        ->assertDispatched('slot-updated');
    
    $this->assertDatabaseHas('music_plan_template_slots', [
        'template_id' => $template->id,
        'slot_id' => $slot->id,
        'sequence' => 2,
        'is_included_by_default' => false,
    ]);
});

test('admin can remove slot from template', function () {
    $admin = User::factory()->admin()->create();
    $template = MusicPlanTemplate::factory()->create();
    $slot = MusicPlanSlot::factory()->create();
    
    $template->slots()->attach($slot->id, [
        'sequence' => 1,
        'is_included_by_default' => true,
    ]);
    
    $this->actingAs($admin)
        ->livewire('pages::admin.music-plan-template-slots', ['template' => $template])
        ->call('removeSlot', $slot->id)
        ->assertDispatched('slot-removed');
    
    $this->assertDatabaseMissing('music_plan_template_slots', [
        'template_id' => $template->id,
        'slot_id' => $slot->id,
    ]);
});

test('slot cannot be added twice to same template', function () {
    $admin = User::factory()->admin()->create();
    $template = MusicPlanTemplate::factory()->create();
    $slot = MusicPlanSlot::factory()->create();
    
    $template->slots()->attach($slot->id, [
        'sequence' => 1,
        'is_included_by_default' => true,
    ]);
    
    $this->actingAs($admin)
        ->livewire('pages::admin.music-plan-template-slots', ['template' => $template])
        ->set('slot_id', $slot->id)
        ->set('sequence', 2)
        ->call('addSlot');
    
    // Should fail due to unique constraint
    $this->assertDatabaseCount('music_plan_template_slots', 1);
});
```

## MusicPlan Model Updates

### Database Migration
Add `template_id` foreign key to `music_plans` table:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('music_plans', function (Blueprint $table) {
            $table->foreignId('template_id')
                ->nullable()
                ->after('user_id')
                ->constrained('music_plan_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('music_plans', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
        });
    }
};
```

### Updated MusicPlan Model
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MusicPlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'template_id', // Added
        'celebration_name',
        'actual_date',
        'setting',
        'season',
        'season_text',
        'week',
        'day',
        'readings_code',
        'year_letter',
        'year_parity',
        'is_published',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actual_date' => 'date',
            'is_published' => 'boolean',
            'season' => 'integer',
            'season_text' => 'string',
            'week' => 'integer',
            'day' => 'integer',
        ];
    }

    /**
     * Get the user that owns the music plan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the template used for this music plan.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MusicPlanTemplate::class);
    }

    /**
     * Get the slots for this music plan.
     */
    public function slots(): BelongsToMany
    {
        return $this->belongsToMany(MusicPlanSlot::class, 'music_plan_slot_assignments')
            ->withPivot(['music_song_id', 'notes', 'sequence'])
            ->orderByPivot('sequence');
    }

    /**
     * Scope for plans using a specific template.
     */
    public function scopeByTemplate($query, $templateId)
    {
        return $query->where('template_id', $templateId);
    }

    /**
     * Scope for plans with templates.
     */
    public function scopeWithTemplate($query)
    {
        return $query->whereNotNull('template_id');
    }

    /**
     * Scope for plans without templates.
     */
    public function scopeWithoutTemplate($query)
    {
        return $query->whereNull('template_id');
    }

    /**
     * Apply template slots to this music plan.
     * Creates slot assignments based on template's default slots.
     */
    public function applyTemplate(): void
    {
        if (!$this->template_id) {
            return;
        }

        $template = $this->template()->with('defaultSlots')->first();
        
        if (!$template) {
            return;
        }

        // Clear existing slot assignments
        $this->slots()->detach();
        
        // Add template's default slots
        foreach ($template->defaultSlots as $slot) {
            $this->slots()->attach($slot->id, [
                'sequence' => $slot->pivot->sequence,
                'music_song_id' => null,
                'notes' => null,
            ]);
        }
    }

    // Existing scopes remain unchanged...
    public function scopePublished($query) { ... }
    public function scopePrivate($query) { ... }
    public function scopeBySetting($query, string $setting) { ... }
    // ... other existing methods
}
```

### MusicPlanSlotAssignment Pivot Table
Create a new table for music plan slot assignments:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_plan_slot_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('music_plan_slots')->restrictOnDelete();
            $table->foreignId('music_song_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->integer('sequence')->default(1);
            $table->timestamps();

            $table->unique(['music_plan_id', 'slot_id']);
            $table->index(['music_plan_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_plan_slot_assignments');
    }
};
```

### MusicPlanSlotAssignment Model
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MusicPlanSlotAssignment extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'music_plan_slot_assignments';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'music_plan_id',
        'slot_id',
        'music_song_id',
        'notes',
        'sequence',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
        ];
    }
}
```

## Future Integration Considerations

### Music Plan Creation Flow
1. User creates a new MusicPlan
2. User can optionally select a template
3. If template is selected:
   - Template's default slots are automatically added to the plan
   - User can add/remove advanced slots as needed
   - User can reorder slots if desired
4. If no template is selected:
   - User manually adds slots to the plan
   - Complete flexibility for custom plans

### Template Selection in UI
- Add template selection dropdown in MusicPlan creation/edit form
- Show template description and slot count when selected
- Provide "Apply Template" button to refresh slots from template
- Warn if applying template will overwrite existing slot assignments

## Validation Rules Summary

### Slot Validation
- `name`: required, string, max:255, unique (when active)
- `description`: nullable, string, max:1000

### Template Validation
- `name`: required, string, max:255, unique (when active)
- `description`: nullable, string, max:1000
- `is_active`: boolean

### Template Slot Validation
- `slot_id`: required, exists:music_plan_slots,id
- `sequence`: required, integer, min:1
- `is_included_by_default`: boolean

### Music Plan Template Selection Validation
- `template_id`: nullable, exists:music_plan_templates,id

## Implementation Plan

### Phase 1: Core Data Models & Migrations
1. **Create database migrations**
   - `music_plan_slots` table
   - `music_plan_templates` table
   - `music_plan_template_slots` pivot table
   - Add `template_id` to `music_plans` table (optional for Phase 1)

2. **Create Eloquent models**
   - `MusicPlanSlot` with SoftDeletes
   - `MusicPlanTemplate` with SoftDeletes
   - `MusicPlanTemplateSlot` pivot model

3. **Create form request classes**
   - `StoreMusicPlanSlotRequest`
   - `UpdateMusicPlanSlotRequest`
   - `StoreMusicPlanTemplateRequest`
   - `UpdateMusicPlanTemplateRequest`
   - `StoreMusicPlanTemplateSlotRequest`
   - `UpdateMusicPlanTemplateSlotRequest`

### Phase 2: Admin Interface
4. **Create Livewire components**
   - `MusicPlanSlots` - Global slot management
   - `MusicPlanTemplates` - Template management
   - `MusicPlanTemplateSlots` - Template slot management

5. **Create Blade views**
   - Corresponding views for each Livewire component
   - Use Flux UI components for consistent styling

6. **Update admin routes**
   - Add routes for slot and template management
   - Update admin navigation layout

7. **Implement soft delete functionality**
   - Add delete confirmation dialogs
   - Implement toggle active status for templates

### Phase 3: Integration with MusicPlans (Optional)
8. **Update MusicPlan model**
   - Add `template_id` relationship
   - Add `applyTemplate()` method
   - Create `music_plan_slot_assignments` table

9. **Update MusicPlan UI**
   - Add template selection dropdown
   - Implement template application logic
   - Show template-based slots in music plan editor

### Phase 4: Testing & Polish
10. **Write comprehensive tests**
    - Feature tests for all admin functionality
    - Test validation rules
    - Test soft delete behavior
    - Test template-slot relationships

11. **Code quality & documentation**
    - Run Laravel Pint for code formatting
    - Add PHPDoc comments
    - Update project documentation

### Implementation Priority
1. **High Priority**: Phases 1 & 2 (Core admin functionality)
   - Allows admin to create/manage slots and templates
   - No impact on existing MusicPlan functionality
   - Can be deployed independently

2. **Medium Priority**: Phase 3 (MusicPlan integration)
   - Connects templates to actual music plans
   - Requires careful migration planning
   - Should be tested thoroughly before deployment

3. **Low Priority**: Phase 4 (Polish & optimization)
   - Performance optimizations
   - Additional UI enhancements
   - Advanced features (drag-and-drop reordering, bulk operations)

### Estimated Files to Create/Modify
```
Database (4 files)
├── migrations/
│   ├── create_music_plan_slots_table.php
│   ├── create_music_plan_templates_table.php
│   ├── create_music_plan_template_slots_table.php
│   └── add_template_id_to_music_plans_table.php (optional)

Models (5 files)
├── MusicPlanSlot.php
├── MusicPlanTemplate.php
├── MusicPlanTemplateSlot.php (pivot)
├── MusicPlanSlotAssignment.php (pivot, optional)
└── MusicPlan.php (modifications)

Form Requests (6 files)
├── StoreMusicPlanSlotRequest.php
├── UpdateMusicPlanSlotRequest.php
├── StoreMusicPlanTemplateRequest.php
├── UpdateMusicPlanTemplateRequest.php
├── StoreMusicPlanTemplateSlotRequest.php
└── UpdateMusicPlanTemplateSlotRequest.php

Livewire Components (3 files)
├── Pages/Admin/MusicPlanSlots.php
├── Pages/Admin/MusicPlanTemplates.php
└── Pages/Admin/MusicPlanTemplateSlots.php

Blade Views (3 files)
├── livewire/pages/admin/music-plan-slots.blade.php
├── livewire/pages/admin/music-plan-templates.blade.php
└── livewire/pages/admin/music-plan-template-slots.blade.php

Routes & Navigation (2 files)
├── routes/admin.php (modifications)
└── resources/views/pages/admin/layout.blade.php (modifications)

Tests (3 files)
├── Feature/MusicPlanTemplates/MusicPlanSlotTest.php
├── Feature/MusicPlanTemplates/MusicPlanTemplateTest.php
└── Feature/MusicPlanTemplates/MusicPlanTemplateSlotTest.php
```

### Success Criteria
- Admin can create, edit, and soft delete music plan slots
- Admin can create, edit, activate/deactivate, and soft delete templates
- Admin can add/remove/reorder slots within templates
- Admin can mark slots as "included by default" or "advanced"
- All data validation works correctly
- Soft delete functionality preserves data integrity
- UI is consistent with existing admin interface
- Tests pass for all new functionality

### Risks & Considerations
1. **Data Integrity**: Soft deletes ensure templates/slots used in existing music plans remain accessible
2. **Performance**: Template-slot relationships should be eager loaded where appropriate
3. **User Experience**: Clear UI for managing complex template-slot relationships
4. **Migration Strategy**: Phase 3 changes require careful planning if existing music plans exist

## Next Steps
1. Review this design document for completeness
2. Approve the implementation plan
3. Switch to Code mode to begin implementation
4. Implement Phase 1 (Core models & migrations)
5. Test functionality before proceeding to Phase 2