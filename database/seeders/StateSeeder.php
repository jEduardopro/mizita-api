<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Addresses\Infrastructure\Eloquent\Models\StateModel;
use App\Shared\Contracts\IdGenerator;
use App\Shared\ValueObjects\CountryCode;
use Illuminate\Database\Seeder;

final class StateSeeder extends Seeder
{
    private const POSITION_STEP = 10;

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

    /**
     * @var array<string, string>
     */
    private const UNITED_STATES = [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const STATES_BY_COUNTRY = [
        CountryCode::Mx->value => self::MEXICAN_STATES,
        CountryCode::Us->value => self::UNITED_STATES,
    ];

    public function __construct(
        private readonly IdGenerator $ids,
    ) {}

    public function run(): void
    {
        foreach (self::STATES_BY_COUNTRY as $countryCode => $states) {
            $this->seedCountry($countryCode, $states);
        }
    }

    /**
     * @param  array<string, string>  $states
     */
    private function seedCountry(string $countryCode, array $states): void
    {
        $position = 0;

        foreach ($states as $code => $name) {
            $this->upsert($countryCode, $code, $name, $position);

            $position += self::POSITION_STEP;
        }
    }

    private function upsert(string $countryCode, string $code, string $name, int $position): void
    {
        $state = StateModel::query()->firstOrNew([
            'country_code' => $countryCode,
            'code' => $code,
        ]);

        $state->uuid ??= $this->ids->next();
        $state->name = $name;
        $state->position = $position;

        $state->save();
    }
}
