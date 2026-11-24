<?php

namespace App\Livewire\Profile;

use App\Support\Audit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Change Password')]
class ChangePassword extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function save()
    {
        $user = auth()->user();

        $this->validate([
            'current_password' => 'required|current_password',
            'password'         => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'The current password is not correct.',
            'password.different'                => 'The new password must be different from the current one.',
        ]);

        $user->forceFill(['password' => Hash::make($this->password), 'must_change_password' => false])->save();
        Audit::log('Users', 'PASSWORD', $user->username, 'Changed own password');

        session()->flash('toast', ['message' => 'Password changed successfully.', 'type' => 'success']);

        return $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.profile.change-password');
    }
}
