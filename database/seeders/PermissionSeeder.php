<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'Voir_abonnement',
            'Ajouter_categorie_abonnement',
            'Modifier_categorie_abonnement',
            'Supprimer_categorie_abonnement',
            'Ajouter_client',
            'Modifier_client',
            'Supprimer_client',
        ];
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }
    }
}
