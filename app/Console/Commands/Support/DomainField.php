<?php

declare(strict_types=1);

namespace App\Console\Commands\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Syntax: name:type[:modifier]... for example "email:email:unique" or
 * "price:decimal(8,2):nullable". Modifiers are nullable, unique and index.
 */
final class DomainField
{
    private const MODIFIERS = ['nullable', 'unique', 'index'];

    /** DSL type => [blueprint method, php type, cast, faker expression]. */
    private const TYPES = [
        'string' => ['string', 'string', null, 'fake()->word()'],
        'text' => ['text', 'string', null, 'fake()->paragraph()'],
        'email' => ['string', 'string', null, 'fake()->unique()->safeEmail()'],
        'integer' => ['integer', 'int', 'integer', 'fake()->numberBetween(1, 100)'],
        'bigInteger' => ['bigInteger', 'int', 'integer', 'fake()->numberBetween(1, 100000)'],
        'boolean' => ['boolean', 'bool', 'boolean', 'fake()->boolean()'],
        'decimal' => ['decimal', 'string', 'decimal', 'fake()->randomFloat(2, 0, 1000)'],
        'float' => ['float', 'float', 'float', 'fake()->randomFloat(2, 0, 1000)'],
        'date' => ['date', 'DateTimeImmutable', 'immutable_date', 'fake()->date()'],
        'datetime' => ['dateTime', 'DateTimeImmutable', 'immutable_datetime', 'fake()->dateTime()'],
        'json' => ['json', 'array', 'array', '[]'],
        'uuid' => ['uuid', 'string', null, 'fake()->uuid()'],
    ];

    /**
     * @param  array<int, string>  $modifiers
     * @param  array<int, string>  $typeArguments  e.g. ['8', '2'] for decimal(8,2)
     */
    private function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $modifiers,
        public readonly array $typeArguments,
    ) {}

    public static function parse(string $definition): self
    {
        $parts = array_values(array_filter(explode(':', $definition), fn (string $p) => $p !== ''));

        if (count($parts) < 2) {
            throw new InvalidArgumentException("Field [{$definition}] must be written as name:type[:modifier].");
        }

        $name = Str::snake(array_shift($parts));
        $rawType = array_shift($parts);
        $typeArguments = [];

        if (preg_match('/^(\w+)\(([^)]*)\)$/', $rawType, $matches) === 1) {
            $rawType = $matches[1];
            $typeArguments = array_map('trim', explode(',', $matches[2]));
        }

        if (! array_key_exists($rawType, self::TYPES)) {
            $known = implode(', ', array_keys(self::TYPES));
            throw new InvalidArgumentException("Unknown field type [{$rawType}] in [{$definition}]. Known types: {$known}.");
        }

        foreach ($parts as $modifier) {
            if (! in_array($modifier, self::MODIFIERS, true)) {
                $known = implode(', ', self::MODIFIERS);
                throw new InvalidArgumentException("Unknown modifier [{$modifier}] in [{$definition}]. Known modifiers: {$known}.");
            }
        }

        return new self($name, $rawType, $parts, $typeArguments);
    }

    public function isNullable(): bool
    {
        return in_array('nullable', $this->modifiers, true);
    }

    public function isUnique(): bool
    {
        return in_array('unique', $this->modifiers, true);
    }

    public function isRequired(): bool
    {
        return ! $this->isNullable();
    }

    /** A field whose emptiness is worth guarding against in the entity. */
    public function isTextual(): bool
    {
        return in_array($this->type, ['string', 'text', 'email'], true);
    }

    public function property(): string
    {
        return Str::camel($this->name);
    }

    public function studly(): string
    {
        return Str::studly($this->name);
    }

    public function phpType(): string
    {
        return ($this->isNullable() ? '?' : '').self::TYPES[$this->type][1];
    }

    public function needsDateImport(): bool
    {
        return self::TYPES[$this->type][1] === 'DateTimeImmutable';
    }

    public function migrationLine(): string
    {
        $method = self::TYPES[$this->type][0];
        $arguments = "'{$this->name}'";

        if ($this->typeArguments !== []) {
            $arguments .= ', '.implode(', ', $this->typeArguments);
        }

        $line = "\$table->{$method}({$arguments})";

        foreach (['nullable', 'unique', 'index'] as $modifier) {
            if (in_array($modifier, $this->modifiers, true)) {
                $line .= "->{$modifier}()";
            }
        }

        return $line.';';
    }

    public function castEntry(): ?string
    {
        $cast = self::TYPES[$this->type][2];

        if ($cast === null) {
            return null;
        }

        if ($cast === 'decimal') {
            $cast .= ':'.($this->typeArguments[1] ?? '2');
        }

        return "'{$this->name}' => '{$cast}',";
    }

    public function factoryEntry(): string
    {
        $faker = self::TYPES[$this->type][3];

        if ($this->type === 'decimal' && isset($this->typeArguments[1])) {
            $faker = "fake()->randomFloat({$this->typeArguments[1]}, 0, 1000)";
        }

        if ($this->isUnique() && ! str_contains($faker, 'unique()')) {
            $faker = str_replace('fake()->', 'fake()->unique()->', $faker);
        }

        return "'{$this->name}' => {$faker},";
    }

    /**
     * @return array<int, string>
     */
    public function validationRules(): array
    {
        $rules = [$this->isNullable() ? 'nullable' : 'required'];

        $rules[] = match ($this->type) {
            'email' => 'email',
            'text', 'string', 'uuid' => 'string',
            'integer', 'bigInteger' => 'integer',
            'boolean' => 'boolean',
            'decimal', 'float' => 'numeric',
            'date' => 'date',
            'datetime' => 'date',
            'json' => 'array',
        };

        if (in_array($this->type, ['string', 'email', 'uuid'], true)) {
            $rules[] = 'max:255';
        }

        return $rules;
    }

    public function validationRulesLine(): string
    {
        $rules = implode(', ', array_map(fn (string $rule) => "'{$rule}'", $this->validationRules()));

        return "'{$this->name}' => [{$rules}],";
    }

    public function requestAccessor(): string
    {
        return match (true) {
            $this->type === 'boolean' => "\$request->boolean('{$this->name}')",
            in_array($this->type, ['integer', 'bigInteger'], true) => $this->isNullable()
                ? "\$request->has('{$this->name}') ? \$request->integer('{$this->name}') : null"
                : "\$request->integer('{$this->name}')",
            $this->needsDateImport() => "new DateTimeImmutable(\$request->string('{$this->name}')->toString())",
            $this->type === 'json' => "\$request->array('{$this->name}')",
            $this->isNullable() => "\$request->string('{$this->name}')->toString() ?: null",
            default => "\$request->string('{$this->name}')->toString()",
        };
    }

    public function resourceValue(): string
    {
        if ($this->needsDateImport()) {
            return $this->isNullable()
                ? "\$this->resource->{$this->property()}?->format(DATE_ATOM)"
                : "\$this->resource->{$this->property()}->format(DATE_ATOM)";
        }

        return "\$this->resource->{$this->property()}";
    }
}
