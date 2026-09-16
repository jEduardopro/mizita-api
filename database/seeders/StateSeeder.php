<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Addresses\Infrastructure\Eloquent\Models\StateModel;
use App\Shared\Contracts\IdGenerator;
use Illuminate\Database\Seeder;

final class StateSeeder extends Seeder
{
    private const POSITION_STEP = 10;

    private const COUNTRY_CODE = 'MX';

    /**
     * @var array<string, string>
     */
    private const MEXICAN_STATES = [
        'AGU' => 'Aguascalientes',
        'BCN' => 'Baja California',
        'BCS' => 'Baja California Sur',
        'CAM' => 'Campeche',
        'CHP' => 'Chiapas',
        'CHH' => 'Chihuahua',
        'CMX' => 'Ciudad de México',
        'COA' => 'Coahuila de Zaragoza',
        'COL' => 'Colima',
        'DUR' => 'Durango',
        'GUA' => 'Guanajuato',
        'GRO' => 'Guerrero',
        'HID' => 'Hidalgo',
        'JAL' => 'Jalisco',
        'MEX' => 'México',
        'MIC' => 'Michoacán de Ocampo',
        'MOR' => 'Morelos',
        'NAY' => 'Nayarit',
        'NLE' => 'Nuevo León',
        'OAX' => 'Oaxaca',
        'PUE' => 'Puebla',
        'QUE' => 'Querétaro',
        'ROO' => 'Quintana Roo',
        'SLP' => 'San Luis Potosí',
        'SIN' => 'Sinaloa',
        'SON' => 'Sonora',
        'TAB' => 'Tabasco',
        'TAM' => 'Tamaulipas',
        'TLA' => 'Tlaxcala',
        'VER' => 'Veracruz de Ignacio de la Llave',
        'YUC' => 'Yucatán',
        'ZAC' => 'Zacatecas',
    ];

    public function __construct(
        private readonly IdGenerator $ids,
    ) {}

    public function run(): void
    {
        $position = 0;

        foreach (self::MEXICAN_STATES as $code => $name) {
            $this->upsert($code, $name, $position);

            $position += self::POSITION_STEP;
        }
    }

    private function upsert(string $code, string $name, int $position): void
    {
        $state = StateModel::query()->firstOrNew([
            'country_code' => self::COUNTRY_CODE,
            'code' => $code,
        ]);

        $state->uuid ??= $this->ids->next();
        $state->name = $name;
        $state->position = $position;

        $state->save();
    }
}
