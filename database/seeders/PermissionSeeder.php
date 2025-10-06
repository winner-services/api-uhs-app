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
            'categorie-abonnement',
            'abonne',
            'raccordement',
            'borne',
            'facturation',
            'paiement',
            'finance',
            'tresorerie',
            'transaction-tresorerie',
            'ticket',
            'mantenance',
            'paramettre',
            'technicien-dashboard',
            'technicien-ticket'
        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
