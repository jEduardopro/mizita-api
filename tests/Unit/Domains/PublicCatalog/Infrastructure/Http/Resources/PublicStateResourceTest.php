<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Http\Resources\PublicStateResource;
use App\Domains\PublicCatalog\ValueObjects\PublicState;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  list<PublicState>  $states
 * @return array<array-key, mixed>
 */
function serializedPublicStates(array $states): array
{
    return (array) PublicStateResource::collection($states)->response()->getData(true)['data'];
}

beforeEach(function () {
    $this->cdmx = new PublicState('01930000-0000-7000-8000-0000000000f1', 'CMX', 'Ciudad de México');
    $this->nuevoLeon = new PublicState('01930000-0000-7000-8000-0000000000f2', 'NLE', 'Nuevo León');
});

describe('the client contract', function () {
    it('serializes a state as exactly its id, its code and its name', function () {
        expect(PublicStateResource::make($this->cdmx)->response()->getData(true)['data'])->toBe([
            'id' => '01930000-0000-7000-8000-0000000000f1',
            'code' => 'CMX',
            'name' => 'Ciudad de México',
        ]);
    });

    it('wraps the list in the data envelope', function () {
        expect(PublicStateResource::collection([$this->cdmx])->response()->getData(true))->toHaveKey('data');
    });

    it('serializes every row of the list with the same key set, in the order it was given', function () {
        expect(serializedPublicStates([$this->nuevoLeon, $this->cdmx]))->toBe([
            ['id' => '01930000-0000-7000-8000-0000000000f2', 'code' => 'NLE', 'name' => 'Nuevo León'],
            ['id' => '01930000-0000-7000-8000-0000000000f1', 'code' => 'CMX', 'name' => 'Ciudad de México'],
        ]);
    });

    it('sends an empty json array, never an object, for a country with no states', function () {
        expect(json_encode(serializedPublicStates([]), JSON_THROW_ON_ERROR))->toBe('[]');
    });
});

describe('what a visitor is allowed to see', function () {
    it('carries neither the country nor the position the catalog sorts by', function (string $forbidden) {
        expect(serializedPublicStates([$this->cdmx])[0])->not->toHaveKey($forbidden);
    })->with(['country_code', 'position', 'active', 'state_id']);

    it('sends the state id as a uuid string, never an internal key', function () {
        $id = serializedPublicStates([$this->cdmx])[0]['id'];

        expect($id)->toBeString()
            ->and(is_numeric($id))->toBeFalse();
    });

    it('keeps accents in the state name', function () {
        expect(serializedPublicStates([$this->nuevoLeon])[0]['name'])->toBe('Nuevo León');
    });
});
