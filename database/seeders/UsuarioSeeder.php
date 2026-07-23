<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('PANEL_EMAIL');
        $password = env('PANEL_PASSWORD');

        if (! is_string($email) || trim($email) === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException(
                'Define PANEL_EMAIL y PANEL_PASSWORD en el .env antes de ejecutar el seeder de usuario.'
            );
        }

        User::query()->updateOrCreate(
            ['email' => trim($email)],
            [
                'name' => 'Operador',
                'password' => $password,
            ]
        );
    }
}
