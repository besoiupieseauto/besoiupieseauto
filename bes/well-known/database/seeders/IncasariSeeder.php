<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Incasari;
use Carbon\Carbon;

class IncasariSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $incasari = [
            [
                'data_comanda' => '2025-03-03',
                'client' => 'PERIAT IONUT',
                'suma_incasata' => 240.00,
                'magazin' => 'Timisoara',
                'metoda_incasare' => 'Card',
            ],
            [
                'data_comanda' => '2025-03-02',
                'client' => 'SURDU MARIUS LIVIU',
                'suma_incasata' => 600.00,
                'magazin' => 'Timisoara',
                'metoda_incasare' => 'Card',
            ],
            [
                'data_comanda' => '2025-01-09',
                'client' => 'LAPUSAN CRISTIAN/LCI SMART COMPUTERS 2014 SRL',
                'suma_incasata' => 735.00,
                'magazin' => 'Timisoara',
                'metoda_incasare' => 'Card',
            ],
            [
                'data_comanda' => '2025-02-27',
                'client' => 'ALINA RADA',
                'suma_incasata' => 330.00,
                'magazin' => 'Utvin',
                'metoda_incasare' => 'Cash',
            ],
        ];

        foreach ($incasari as $incasare) {
            Incasari::create($incasare);
        }
    }
}