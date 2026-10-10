<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocalDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Este seeder deve ser executado apenas no ambiente local.');
        }

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Unidade Demo', 'is_active' => true],
        );

        foreach (['admin' => 'Administrador Demo', 'admin-sistema' => 'Administrador do Sistema'] as $role => $name) {
            User::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => $role.'@formengine.local'],
                [
                    'name' => $name,
                    'password' => 'FormEngineLocal123!',
                    'role' => $role,
                    'is_active' => true,
                ],
            );
        }
    }
}
