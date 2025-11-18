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
            'categorie-abonne',
            'abonne',
            'raccordement',
            'borne-fontaine',
            'bornier-regulier',
            'facturation',
            'finance',
            'tresorerie',
            'transaction-tresorerie',
            'paiement-mantenance',
            'logistique',
            'mantenance',
            'rapport-mantenance',
            'paramettre',
            'agent',
            'role-agent',
            'technicien-dashboard',
            'technicien-ticket'
        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
