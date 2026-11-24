<?php

namespace App\Livewire\Users;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Users')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    // form
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $phone = '';
    public string $role = User::STAFF;
    public bool $is_active = true;
    public string $password = '';

    /** Temporary password shown once after creating a user / resetting a password. */
    public ?array $issued = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->password = $this->tempPassword();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $u = User::findOrFail($id);
        $this->resetForm();
        $this->editingId = $u->id;
        $this->fill($u->only(['name', 'username', 'role', 'is_active']));
        $this->email = (string) $u->email;
        $this->phone = (string) $u->phone;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name'      => 'required|string|max:120',
            'username'  => ['required', 'regex:/^[A-Za-z0-9._-]+$/', 'max:50', Rule::unique('users', 'username')->ignore($this->editingId)],
            'email'     => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone'     => 'nullable|string|max:30',
            'role'      => ['required', Rule::in(array_keys(User::ROLES))],
            'is_active' => 'boolean',
            'password'  => $this->editingId ? 'nullable' : 'required|string|min:8',
        ], ['username.regex' => 'Username may contain letters, numbers, dots, dashes and underscores only.']);
        $data['username'] = Str::lower($data['username']);
        $data['email'] = $data['email'] ?: null;

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            if ($problem = $this->adminProblem($user, $data['role'], $data['is_active'])) {
                $this->addError('role', $problem);

                return;
            }
            unset($data['password']);
            $user->update($data);
            Audit::log('Users', 'UPDATE', $user->username, 'Role: '.$user->role.', Active: '.($user->is_active ? 'yes' : 'no'));
            $this->dispatch('toast', message: 'User updated.', type: 'success');
        } else {
            $plain = $data['password'];
            $user = User::create($data + ['must_change_password' => true]);
            Audit::log('Users', 'CREATE', $user->username, 'Role: '.$user->role);
            $this->issued = ['name' => $user->name, 'username' => $user->username, 'password' => $plain];
        }

        $this->showForm = false;
    }

    public function toggleActive(int $id): void
    {
        $user = User::findOrFail($id);
        if ($problem = $this->adminProblem($user, $user->role, ! $user->is_active)) {
            $this->dispatch('toast', message: $problem, type: 'error');

            return;
        }
        $user->update(['is_active' => ! $user->is_active]);
        Audit::log('Users', $user->is_active ? 'ACTIVATE' : 'DEACTIVATE', $user->username);
        $this->dispatch('toast', message: $user->name.' is now '.($user->is_active ? 'active' : 'inactive').'.', type: 'success');
    }

    public function resetPassword(int $id): void
    {
        $user = User::findOrFail($id);
        $plain = $this->tempPassword();
        $user->forceFill(['password' => Hash::make($plain), 'must_change_password' => true])->save();
        Audit::log('Users', 'RESET PASSWORD', $user->username);
        $this->issued = ['name' => $user->name, 'username' => $user->username, 'password' => $plain];
    }

    /** Returns a message when the change would leave the system without an active administrator. */
    private function adminProblem(User $user, string $newRole, bool $newActive): ?string
    {
        if ($user->id === auth()->id() && (! $newActive || $newRole !== User::ADMIN)) {
            return 'You cannot remove your own admin access or deactivate yourself.';
        }
        $wasActiveAdmin = $user->role === User::ADMIN && $user->is_active;
        $staysActiveAdmin = $newRole === User::ADMIN && $newActive;
        if ($wasActiveAdmin && ! $staysActiveAdmin
            && User::where('role', User::ADMIN)->where('is_active', true)->count() <= 1) {
            return 'At least one active administrator is required.';
        }

        return null;
    }

    private function tempPassword(): string
    {
        // easy to read aloud: e.g. "Luxe-4821-kq"
        return 'Luxe-'.random_int(1000, 9999).'-'.Str::lower(Str::random(2));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'username', 'email', 'phone', 'password']);
        $this->role = User::STAFF;
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('username', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(15);

        return view('livewire.users.index', ['users' => $users, 'roles' => User::ROLES]);
    }
}
