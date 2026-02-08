<?php

namespace App\Livewire\Pages\Admin;

use App\Models\User;
use Livewire\Component;

class Users extends Component
{
    public function render()
    {
        $users = User::latest()->get();

        return view('livewire.pages.admin.users', ['users' => $users]);
    }
}
