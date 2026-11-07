<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Module\AcademicMobility\Core\Models\Institution;

/**
 * Demo institutions: the universities of Santiago de Cali (Universidad Santiago
 * de Cali is the local one), others from the rest of Colombia and some abroad,
 * so mobilities of every direction and scope can be tried.
 *
 * Idempotent: it matches by name, so it can run many times.
 * Use `php artisan db:seed --class=InstitutionSeeder`.
 */
class InstitutionSeeder extends Seeder
{
    private const LOCAL = 'Universidad Santiago de Cali';

    /** @var array<string, array<int, string>> ISO country => names */
    private const PARTNERS = [
        // Santiago de Cali
        'CO' => [
            'Universidad del Valle',
            'Pontificia Universidad Javeriana Cali',
            'Universidad Icesi',
            'Universidad Autónoma de Occidente',
            'Universidad Libre Seccional Cali',
            'Universidad de San Buenaventura Cali',
            'Universidad Cooperativa de Colombia - Sede Cali',
            'Universidad Antonio Nariño - Sede Cali',
            'Fundación Universitaria Católica Lumen Gentium',
            'Institución Universitaria Antonio José Camacho',
            'Instituto Departamental de Bellas Artes',
            'Escuela Nacional del Deporte',
            'Conservatorio Antonio María Valencia',
            'Fundación Universitaria San Martín - Sede Cali',
            'UNIMINUTO - Sede Cali',
            'SENA Regional Valle del Cauca',
            'Universidad Nacional Abierta y a Distancia - Sede Cali',
            'ESAP Territorial Valle del Cauca',
            'Intenalco Educación Superior',
            // Rest of Colombia
            'Universidad Nacional de Colombia',
            'Universidad de los Andes',
            'Pontificia Universidad Javeriana',
            'Universidad de Antioquia',
            'Universidad EAFIT',
            'Universidad del Rosario',
            'Universidad Industrial de Santander',
            'Universidad del Norte',
            'Universidad Tecnológica de Pereira',
            'Universidad de Cartagena',
        ],
        // Abroad: Colombia is the local country, so these are international.
        'ES' => ['Universidad de Salamanca', 'Universidad Complutense de Madrid'],
        'AR' => ['Universidad de Buenos Aires'],
        'MX' => ['Universidad Nacional Autónoma de México'],
        'CL' => ['Universidad de Chile'],
        'BR' => ['Universidade de São Paulo'],
        'FR' => ['Sorbonne Université'],
        'DE' => ['Technische Universität München'],
        'CA' => ['University of Toronto'],
        'US' => ['Massachusetts Institute of Technology'],
    ];

    public function run(): void
    {
        foreach (self::PARTNERS as $country => $names) {
            foreach ($names as $name) {
                Institution::query()->firstOrCreate(['name' => $name], ['country' => $country]);
            }
        }

        // Last, so the local one is promoted in a single, valid step.
        Institution::query()->firstOrCreate(['name' => self::LOCAL], ['country' => 'CO'])->makeLocal();
    }
}
