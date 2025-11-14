<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompteComptableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Remise en banque',
            'virement bancaire',
            'Transfert de caisse',
            'Retrait bancaire'
        ];
    }
}
