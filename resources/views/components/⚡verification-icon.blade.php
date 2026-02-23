<?php

use Livewire\Component;
use App\Models\Music;

new class extends Component
{
    public string $fieldName;
    public Music $music;
    public ?string $pivotReference = null;
    
    public function mount(string $fieldName, Music $music, ?string $pivotReference = null): void
    {
        $this->fieldName = $fieldName;
        $this->music = $music;
        $this->pivotReference = $pivotReference;
    }
    
    public function isVerified(): bool
    {
        $query = $this->music->verifications()
            ->where('field_name', $this->fieldName);
            
        if ($this->pivotReference !== null) {
            $query->where('pivot_reference', $this->pivotReference);
        }
        
        return $query->where('status', 'verified')->exists();
    }
    
    public function render()
    {
        return view('components.⚡verification-icon');
    }
};
?>

<div>
    @if($this->isVerified())
        <flux:icon name="check" variant="solid" class="inline h-5 w-5 text-green-500" />
    @endif
</div>