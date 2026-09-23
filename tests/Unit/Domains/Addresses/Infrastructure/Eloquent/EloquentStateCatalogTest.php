<?php

declare(strict_types=1);

use App\Domains\Addresses\Infrastructure\Eloquent\EloquentStateCatalog;
use App\Domains\Addresses\Infrastructure\Eloquent\Mappers\StateMapper;
use App\Domains\Addresses\Services\StateMatcher;
use App\Shared\ValueObjects\CountryCode;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->previousResolver = Model::getConnectionResolver();

    $this->resolver = new class implements ConnectionResolverInterface
    {
        public int $connectionsRequested = 0;

        public function connection($name = null)
        {
            $this->connectionsRequested++;

            throw new RuntimeException('The state catalog reached for a database connection.');
        }

        public function getDefaultConnection()
        {
            return 'unreachable';
        }

        public function setDefaultConnection($name) {}
    };

    Model::setConnectionResolver($this->resolver);

    $this->catalog = new EloquentStateCatalog(new StateMapper, new StateMatcher);
});

afterEach(function () {
    if ($this->previousResolver === null) {
        Model::unsetConnectionResolver();

        return;
    }

    Model::setConnectionResolver($this->previousResolver);
});

describe('a blank name or code', function () {
    it('finds no state', function (CountryCode $preferredCountry, string $nameOrCode) {
        expect($this->catalog->findActiveByNameOrCodePreferring($preferredCountry, $nameOrCode))->toBeNull();
    })->with([
        'empty, preferring Mexico' => [CountryCode::Mx, ''],
        'spaces, preferring Mexico' => [CountryCode::Mx, '   '],
        'tabs and newlines, preferring Mexico' => [CountryCode::Mx, "\t\n\r "],
        'empty, preferring the United States' => [CountryCode::Us, ''],
        'spaces, preferring the United States' => [CountryCode::Us, '   '],
    ]);

    it('never reaches for the database', function (string $nameOrCode) {
        $this->catalog->findActiveByNameOrCodePreferring(CountryCode::Mx, $nameOrCode);

        expect($this->resolver->connectionsRequested)->toBe(0);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tabs and newlines' => "\t\n\r ",
        'non-breaking space' => "\u{00A0}",
        'em space and non-breaking space' => "\u{2003}\u{00A0} ",
    ]);
});

it('reaches for the catalog once the text carries a name', function () {
    expect(fn () => $this->catalog->findActiveByNameOrCodePreferring(CountryCode::Mx, 'Texas'))
        ->toThrow(RuntimeException::class, 'The state catalog reached for a database connection.')
        ->and($this->resolver->connectionsRequested)->toBe(1);
});
