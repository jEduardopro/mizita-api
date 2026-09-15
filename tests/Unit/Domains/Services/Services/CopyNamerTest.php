<?php

declare(strict_types=1);

use App\Domains\Services\Services\CopyNamer;

beforeEach(function () {
    $this->namer = new CopyNamer;
    $this->allocate = fn (array $taken, string $base = 'Corte de pelo (Copy)'): string => $this->namer
        ->allocate($base, $taken);
});

it('hands back the base name when nothing has taken it', function () {
    expect(($this->allocate)([]))->toBe('Corte de pelo (Copy)');
});

it('numbers the name from two when the base is taken', function () {
    expect(($this->allocate)(['Corte de pelo (Copy)']))->toBe('Corte de pelo (Copy) 2');
});

it('walks past every number already taken', function () {
    expect(($this->allocate)([
        'Corte de pelo (Copy)',
        'Corte de pelo (Copy) 2',
        'Corte de pelo (Copy) 3',
    ]))->toBe('Corte de pelo (Copy) 4');
});

it('fills the first gap in the numbering', function () {
    expect(($this->allocate)(['Corte de pelo (Copy)', 'Corte de pelo (Copy) 4']))
        ->toBe('Corte de pelo (Copy) 2');
});

it('matches a taken name whatever its case, because the database unique folds it', function () {
    expect(($this->allocate)(['corte de pelo (copy)']))->toBe('Corte de pelo (Copy) 2');
});

it('ignores a name that merely starts like the base', function () {
    expect(($this->allocate)(['Corte de pelo (Copy) premium', 'Corte de pelo (Copy)2']))
        ->toBe('Corte de pelo (Copy)');
});

it('reads a taken name through the whitespace around it', function () {
    expect(($this->allocate)(['  Corte de pelo (Copy)  ']))->toBe('Corte de pelo (Copy) 2');
});

it('reads a base that carries characters a pattern would otherwise take as syntax', function () {
    expect(($this->allocate)(['Corte (Copia) +1'], 'Corte (Copia) +1'))->toBe('Corte (Copia) +1 2');
});

it('numbers an accented name the same way', function () {
    expect(($this->allocate)(['Barbería Ñandú'], 'Barbería Ñandú'))->toBe('Barbería Ñandú 2');
});
