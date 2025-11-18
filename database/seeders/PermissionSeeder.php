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
            'abonnement',
            'categorie_abonne',
            'abonne',
            'raccordement',
            'borne_fontaine',
            'bornier_regulier',
            'facturation',
            'finance',
            'tresorerie',
            'transaction_tresorerie',
            'paiement_mantenance',
            'logistique',
            'mantenance',
            'rapport_mantenance',
            'paramettre',
            'agent',
            'role_agent',
            'technicien_dashboard',
            'technicien_ticket'
        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
