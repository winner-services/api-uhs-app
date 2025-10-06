<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolesPermissions = [
            'admin' => [
                'abonnement',
                'categorie_abonnement',
                'abonne',
                'raccordement',
                'borne',
                'facturation',
                'paiement',
                'finance',
                'tresorerie',
                'transaction_tresorerie',
                'ticket',
                'mantenance',
                'paramettre',
            ],
            'technicien' => [
                'technicien_dashboard',
                'technicien_ticket'
            ],
        ];

        foreach ($rolesPermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $permissionsModels = Permission::whereIn('name', $permissions)->get();

            $role->syncPermissions($permissionsModels);
        }
    }
}
