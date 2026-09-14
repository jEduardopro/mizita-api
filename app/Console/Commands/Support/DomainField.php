<?php

declare(strict_types=1);

namespace App\Console\Commands\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class DomainField
{
    public const MAXIMUM_TEXT_LENGTH = 255;

    public const MAXIMUM_EMAIL_LENGTH = 254;

    private const MODIFIERS = ['nullable', 'unique', 'index'];

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
     * @param  array<int, string>  $typeArguments
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

        if ($this->hasMaximumLength() || $this->type === 'uuid') {
            $rules[] = 'max:'.$this->maximumLength();
        }

        return $rules;
    }

    public function validationRulesLine(): string
    {
        $rules = implode(', ', array_map(fn (string $rule) => "'{$rule}'", $this->validationRules()));

        return "'{$this->name}' => [{$rules}],";
    }

    public function readsAsText(): bool
    {
        return in_array($this->type, ['string', 'text', 'email', 'uuid'], true);
    }

    public function payloadAccessor(): string
    {
        $value = "\$payload['{$this->name}'] ?? null";

        if ($this->readsAsText()) {
            return $this->isNullable()
                ? sprintf('self::%sOrNull(%s)', $this->property(), $value)
                : sprintf('self::textOrEmpty(%s)', $value);
        }

        if ($this->needsDateImport()) {
            return $this->isNullable()
                ? sprintf('self::%sOrNull(%s)', $this->property(), $value)
                : sprintf('self::%sOrNow(%s)', $this->property(), $value);
        }

        if ($this->type === 'decimal') {
            return $this->isNullable()
                ? sprintf('self::decimalOrNull(%s)', $value)
                : sprintf('self::decimalOrEmpty(%s)', $value);
        }

        $key = "\$payload['{$this->name}']";

        if ($this->isNullable()) {
            return match ($this->type) {
                'integer', 'bigInteger' => "isset({$key}) ? (int) {$key} : null",
                'boolean' => "isset({$key}) ? (bool) {$key} : null",
                'float' => "isset({$key}) ? (float) {$key} : null",
                'json' => "isset({$key}) ? (array) {$key} : null",
            };
        }

        return match ($this->type) {
            'integer', 'bigInteger' => "(int) ({$key} ?? 0)",
            'boolean' => "(bool) ({$key} ?? false)",
            'float' => "(float) ({$key} ?? 0.0)",
            'json' => "(array) ({$key} ?? [])",
        };
    }

    public function isValidatable(): bool
    {
        return $this->validationFailures() !== [];
    }

    public function refusesUnreadableText(): bool
    {
        return $this->readsAsText() && $this->isNullable();
    }

    public function hasMaximumLength(): bool
    {
        return in_array($this->type, ['string', 'email'], true);
    }

    public function maximumLength(): int
    {
        return $this->type === 'email' ? self::MAXIMUM_EMAIL_LENGTH : self::MAXIMUM_TEXT_LENGTH;
    }

    public function needsUuidPattern(): bool
    {
        return $this->type === 'uuid';
    }

    public function maximumLengthConstant(): string
    {
        return 'MAXIMUM_'.strtoupper($this->name).'_LENGTH';
    }

    /**
     * @return array<int, string>
     */
    public function validationFailures(): array
    {
        if (! in_array($this->type, ['string', 'text', 'email', 'uuid'], true)) {
            return [];
        }

        $failures = [];

        if ($this->isRequired() && ! $this->needsUuidPattern()) {
            $failures[] = 'empty';
        }

        if (in_array($this->type, ['email', 'uuid'], true)) {
            $failures[] = 'malformed';
        }

        if ($this->hasMaximumLength()) {
            $failures[] = 'tooLong';
        }

        return $failures;
    }

    /**
     * @return array<int, string>
     */
    public function constructorFailures(): array
    {
        $failures = $this->validationFailures();

        if ($this->refusesUnreadableText() && ! in_array('malformed', $failures, true)) {
            array_unshift($failures, 'malformed');
        }

        return $failures;
    }

    /**
     * @return array<int, string>
     */
    public function validatorBody(string $exception): array
    {
        $property = '$this->'.$this->property();
        $lines = [];

        if ($this->isNullable()) {
            $lines[] = "if ({$property} === null) {";
            $lines[] = '    return;';
            $lines[] = '}';
            $lines[] = '';
            $subject = $property;
        } elseif (in_array('empty', $this->validationFailures(), true)) {
            $subject = '$'.$this->property();
            $lines[] = "{$subject} = trim({$property});";
            $lines[] = '';
            $lines[] = "if ({$subject} === '') {";
            $lines[] = "    throw {$exception}::empty();";
            $lines[] = '}';
            $lines[] = '';
        } else {
            $subject = $property;
        }

        if ($this->hasMaximumLength()) {
            $lines[] = sprintf('if (mb_strlen(%s) > self::%s) {', $subject, $this->maximumLengthConstant());
            $lines[] = "    throw {$exception}::tooLong();";
            $lines[] = '}';
            $lines[] = '';
        }

        if ($this->type === 'email') {
            $lines[] = "if (filter_var({$subject}, FILTER_VALIDATE_EMAIL) === false) {";
            $lines[] = "    throw {$exception}::malformed();";
            $lines[] = '}';
            $lines[] = '';
        }

        if ($this->needsUuidPattern()) {
            $lines[] = "if (preg_match(self::UUID_PATTERN, {$subject}) !== 1) {";
            $lines[] = "    throw {$exception}::malformed();";
            $lines[] = '}';
            $lines[] = '';
        }

        array_pop($lines);

        return $lines;
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
