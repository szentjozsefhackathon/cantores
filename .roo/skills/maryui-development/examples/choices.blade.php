This component is intended to be used to build complex selection interfaces for single and multiple values. It also supports search on frontend or server, when dealing with large lists.
By default, it will look up for:
$object->id for option value.
$object->name for option display label.
$object->avatar for avatar picture.


Slots
You have full control on rendering items by using the @scope('item', $object) special blade directive. It injects the current $object from the loop's context and achieves the same behavior that you would expect from the Vue/React scoped slots.

You can customize the list item and selected item slot. Searchable (online) works with blade syntax.

Slots (online)
Joy (ashton.olson) 
<x-choices label="Slots (online)" wire:model="user_custom_slot_id" :options="$users" single>
    {{-- Item slot --}}
    @scope('item', $user)
        <x-list-item :item="$user" sub-value="bio">
            <x-slot:avatar>
                <x-icon name="o-user" class="bg-primary/10 p-2 w-9 h-9 rounded-full" />
            </x-slot:avatar>
            <x-slot:actions>
                <x-badge :value="$user->username" class="badge-soft badge-primary badge-sm" />
            </x-slot:actions>
        </x-list-item>
    @endscope

    {{-- Selection slot--}}
    @scope('selection', $user)
        {{ $user->name }} ({{ $user->username }})
    @endscope
</x-choices>