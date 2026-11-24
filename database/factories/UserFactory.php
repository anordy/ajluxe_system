<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'                 => fake()->name(),
            'username'             => Str::lower(fake()->unique()->userName()),
            'email'                => fake()->unique()->safeEmail(),
            'role'                 => User::STAFF,
            'is_active'            => true,
            'must_change_password' => false,
            'password'             => static::$password ??= Hash::make('password'),
            'remember_token'       => Str::random(10),
        ];
    }

    public function role(string $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function admin(): static
    {
        return $this->role(User::ADMIN);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
