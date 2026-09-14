<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Support\DomainField;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class MakeDomainCommand extends Command
{
    protected $signature = 'make:domain
        {name : Domain name, usually plural (e.g. Customers)}
        {--field=* : Column as name:type[:modifier], e.g. email:email:unique}
        {--entity= : Entity class name; defaults to the singular of the domain}
        {--root : Global domain, not scoped to a business}
        {--force : Overwrite files if the domain already exists}';

    protected $description = 'Generate a new DDD domain module under app/Domains';

    /** Stub => target path, relative to app/Domains/{domain}. Conditional stubs are added by domainFiles(). */
    private const DOMAIN_FILES = [
        'contract.repository.stub' => 'Contracts/{{ entity }}Repository.php',
        'entity.stub' => 'Entities/{{ entity }}.php',
        'exception.not-found.stub' => 'Exceptions/{{ entity }}NotFound.php',
        'event.created.stub' => 'Events/{{ entity }}Created.php',
        'dto.input.stub' => 'Application/Dtos/Create{{ entity }}Input.php',
        'dto.data.stub' => 'Application/Dtos/{{ entity }}Data.php',
        'usecase.stub' => 'Application/UseCases/Create{{ entity }}.php',
        'model.stub' => 'Infrastructure/Eloquent/Models/{{ entity }}Model.php',
        'factory.stub' => 'Infrastructure/Eloquent/Factories/{{ entity }}ModelFactory.php',
        'mapper.stub' => 'Infrastructure/Eloquent/Mappers/{{ entity }}Mapper.php',
        'repository.eloquent.stub' => 'Infrastructure/Eloquent/Eloquent{{ entity }}Repository.php',
        'controller.stub' => 'Infrastructure/Http/Controllers/{{ entity }}Controller.php',
        'request.stub' => 'Infrastructure/Http/Requests/Create{{ entity }}Request.php',
        'resource.stub' => 'Infrastructure/Http/Resources/{{ entity }}Resource.php',
        'routes.stub' => 'Infrastructure/Http/routes.php',
        'provider.stub' => '{{ domain }}ServiceProvider.php',
    ];

    /** Stub => target path, relative to the app path. */
    private const SHARED_FILES = [
        'contract.clock.stub' => 'Shared/Contracts/Clock.php',
        'contract.id-generator.stub' => 'Shared/Contracts/IdGenerator.php',
        'contract.transaction-manager.stub' => 'Shared/Contracts/TransactionManager.php',
        'contract.business-context.stub' => 'Shared/Contracts/BusinessContext.php',
        'impl.system-clock.stub' => 'Shared/Infrastructure/SystemClock.php',
        'impl.uuid-generator.stub' => 'Shared/Infrastructure/UuidGenerator.php',
        'impl.eloquent-transaction-manager.stub' => 'Shared/Infrastructure/EloquentTransactionManager.php',
        'impl.request-business-context.stub' => 'Shared/Infrastructure/RequestBusinessContext.php',
        'concern.belongs-to-business.stub' => 'Shared/Infrastructure/Concerns/BelongsToBusiness.php',
    ];

    /** BusinessContext is absent on purpose: SetBusinessContext binds it per request. */
    private const SHARED_BINDINGS = [
        'Clock' => 'SystemClock',
        'IdGenerator' => 'UuidGenerator',
        'TransactionManager' => 'EloquentTransactionManager',
    ];

    /** Folder names the flat pre-layered layout used at a domain root. */
    private const LEGACY_FOLDERS = ['Dtos', 'UseCases', 'Jobs', 'Commands', 'Listeners'];

    /** @var array<int, string> paths written during this run, formatted with Pint before finishing */
    private array $touched = [];

    /** @var array<int, DomainField> */
    private array $fields = [];

    private bool $tenantScoped = true;

    public function handle(Filesystem $files): int
    {
        $domain = Str::studly((string) $this->argument('name'));
        $entity = Str::studly((string) ($this->option('entity') ?: Str::singular($domain)));
        $this->tenantScoped = ! $this->option('root');

        try {
            $this->fields = $this->resolveFields();
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $domainPath = app_path("Domains/{$domain}");

        if (! $this->option('force') && $this->hasPhpFiles($files, $domainPath)) {
            $this->components->error("Domain [{$domain}] already contains PHP files. Use --force to overwrite.");

            return self::FAILURE;
        }

        $replacements = $this->buildReplacements($domain, $entity);

        $this->components->info(sprintf(
            'Scaffolding %s domain [%s] with entity [%s] and %d field(s).',
            $this->tenantScoped ? 'tenant-scoped' : 'root',
            $domain,
            $entity,
            count($this->fields),
        ));

        $this->writeSharedKernel($files, $replacements);
        $this->writeDomainFiles($files, $domainPath, $replacements);
        $this->writeMigration($files, $replacements['{{ table }}'], $replacements);
        $this->registerProvider($files, $domain);
        $this->warnAboutLegacyFolders($files, $domainPath, $domain);
        $this->warnAboutMigrationOrder($files, $replacements['{{ table }}']);
        $this->format();

        $this->newLine();
        $this->components->info('Done. Next steps:');
        $this->components->bulletList(array_filter([
            'Review the generated migration, then run migrate.',
            "Replace the placeholder invariants in Entities/{$entity}.php with the real business rules.",
            "Adjust Contracts/{$entity}Repository.php to the queries the domain actually needs.",
            $this->tenantScoped ? 'Routes require an authenticated user with a business (auth:sanctum + business middleware).' : null,
        ]));

        return self::SUCCESS;
    }

    /**
     * A non-TTY caller has no way to answer a prompt, so it must always pass --field.
     *
     * @return array<int, DomainField>
     */
    private function resolveFields(): array
    {
        /** @var array<int, string> $raw */
        $raw = $this->option('field');

        if ($raw !== []) {
            return array_map(fn (string $definition) => DomainField::parse($definition), $raw);
        }

        if ($this->input->isInteractive()) {
            return $this->promptForFields();
        }

        $this->components->warn('No --field given and no terminal to prompt: falling back to a single "name:string" column.');

        return [DomainField::parse('name:string')];
    }

    /**
     * @return array<int, DomainField>
     */
    private function promptForFields(): array
    {
        $fields = [];

        while (true) {
            $name = text(
                label: 'Field name',
                placeholder: 'email',
                hint: $fields === [] ? 'Leave empty to finish.' : 'Leave empty to finish. '.count($fields).' field(s) so far.',
            );

            if (trim($name) === '') {
                break;
            }

            $type = select(
                label: "Type for [{$name}]",
                options: ['string', 'text', 'email', 'integer', 'boolean', 'decimal(8,2)', 'float', 'date', 'datetime', 'json', 'uuid'],
                default: 'string',
            );

            $modifiers = multiselect(
                label: "Modifiers for [{$name}]",
                options: ['nullable', 'unique', 'index'],
            );

            $fields[] = DomainField::parse(implode(':', [$name, $type, ...$modifiers]));
        }

        if ($fields === []) {
            $this->components->warn('No fields declared: falling back to a single "name:string" column.');

            return [DomainField::parse('name:string')];
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    private function buildReplacements(string $domain, string $entity): array
    {
        $variable = Str::camel($entity);
        $guard = $this->guardField();
        $unique = $this->uniqueField();
        $activeField = $this->activeField();

        $replacements = [
            '{{ domain }}' => $domain,
            '{{ entity }}' => $entity,
            '{{ table }}' => Str::snake(Str::plural($entity)),
            '{{ routePrefix }}' => Str::kebab(Str::plural($entity)),
            '{{ variable }}' => $variable,
            '{{ variablePlural }}' => Str::camel(Str::plural($entity)),
            '{{ guardField }}' => $guard?->studly() ?? '',
            '{{ guardFieldLabel }}' => $guard !== null ? str_replace('_', ' ', $guard->name) : '',
            '{{ uniqueField }}' => $unique?->studly() ?? '',
            '{{ uniqueFieldLabel }}' => $unique !== null ? str_replace('_', ' ', $unique->name) : '',
        ];

        $replacements['{{ migrationFields }}'] = $this->indentLines(
            array_map(fn (DomainField $f) => $f->migrationLine(), $this->fields),
            12,
        );
        $replacements['{{ migrationBusinessField }}'] = $this->tenantScoped
            ? $this->indentLines([
                '// Tenant discriminator: references businesses.uuid, not its int key.',
                "\$table->uuid('business_id')->index();",
                "\$table->foreign('business_id')->references('uuid')->on('businesses')->cascadeOnDelete();",
            ], 12)."\n"
            : '';

        $fillable = ["'uuid'"];
        if ($this->tenantScoped) {
            $fillable[] = "'business_id'";
        }
        foreach ($this->fields as $field) {
            $fillable[] = "'{$field->name}'";
        }
        $replacements['{{ fillable }}'] = implode(', ', $fillable);

        $casts = array_values(array_filter(array_map(fn (DomainField $f) => $f->castEntry(), $this->fields)));
        $replacements['{{ casts }}'] = $this->indentLines($casts, 12);

        $replacements['{{ modelTraits }}'] = $this->tenantScoped ? "    use BelongsToBusiness;\n" : '';
        $replacements['{{ modelImports }}'] = $this->tenantScoped
            ? "use App\\Shared\\Infrastructure\\Concerns\\BelongsToBusiness;\n"
            : '';

        $replacements['{{ factoryFields }}'] = $this->indentLines(
            array_map(fn (DomainField $f) => $f->factoryEntry(), $this->fields),
            12,
        );
        $replacements['{{ factoryBusinessField }}'] = $this->tenantScoped
            ? $this->indentLines(["'business_id' => fn () => BusinessModel::factory()->create()->uuid,"], 12)."\n"
            : '';
        $replacements['{{ factoryImports }}'] = $this->tenantScoped
            ? "use App\\Domains\\Businesses\\Infrastructure\\Eloquent\\Models\\BusinessModel;\n"
            : '';

        $replacements['{{ entityImports }}'] = $this->importBlock(['DateTimeImmutable', ...$this->exceptionImports($domain, $guard, $activeField)]);
        $replacements['{{ entityConstructorParams }}'] = $this->indentLines(array_merge(
            $this->tenantScoped ? ['public readonly string $businessId,'] : [],
            array_map(
                fn (DomainField $f) => sprintf('private %s $%s,', $f->phpType(), $f->property()),
                $this->fields,
            ),
        ), 8);

        $signature = array_merge(
            ['string $id,'],
            $this->tenantScoped ? ['string $businessId,'] : [],
            array_map(fn (DomainField $f) => sprintf('%s $%s,', $f->phpType(), $f->property()), $this->fields),
        );
        $replacements['{{ entityCreateParams }}'] = $this->indentLines($signature, 8);
        $replacements['{{ entityRestoreParams }}'] = $this->indentLines($signature, 8);

        $arguments = array_merge(
            ['id: $id,'],
            $this->tenantScoped ? ['businessId: $businessId,'] : [],
            array_map(fn (DomainField $f) => sprintf('%s: $%s,', $f->property(), $f->property()), $this->fields),
        );
        $replacements['{{ entityCreateArgs }}'] = $this->indentLines($arguments, 12);
        $replacements['{{ entityRestoreArgs }}'] = $this->indentLines($arguments, 12);

        $replacements['{{ entityInvariants }}'] = $guard === null ? '' : $this->indentLines([
            sprintf('$%s = trim($%s);', $guard->property(), $guard->property()),
            '',
            sprintf('if ($%s === \'\') {', $guard->property()),
            sprintf('    throw Invalid%s%s::empty();', $entity, $guard->studly()),
            '}',
            '',
        ], 8);

        $getters = [];
        foreach ($this->fields as $field) {
            $getters[] = '';
            $getters[] = sprintf('    public function %s(): %s', $field->property(), $field->phpType());
            $getters[] = '    {';
            $getters[] = sprintf('        return $this->%s;', $field->property());
            $getters[] = '    }';
        }
        $replacements['{{ entityGetters }}'] = implode("\n", $getters);

        $replacements['{{ entityBehavior }}'] = $activeField === null ? '' : implode("\n", [
            '',
            '    /**',
            '     * Business state, distinct from the soft delete on the record.',
            '     *',
            sprintf('     * @throws %sAlreadyInactive', $entity),
            '     */',
            '    public function deactivate(): void',
            '    {',
            sprintf('        if (! $this->%s) {', $activeField->property()),
            sprintf('            throw %sAlreadyInactive::for($this->id);', $entity),
            '        }',
            '',
            sprintf('        $this->%s = false;', $activeField->property()),
            '    }',
        ]);

        $replacements['{{ inputDtoImports }}'] = $this->importBlock($this->dateImport());
        $replacements['{{ inputDtoProperties }}'] = $this->indentLines(
            array_map(fn (DomainField $f) => sprintf('public %s $%s,', $f->phpType(), $f->property()), $this->fields),
            8,
        );
        $replacements['{{ dataDtoProperties }}'] = $this->indentLines(array_merge(
            $this->tenantScoped ? ['public string $businessId,'] : [],
            array_map(fn (DomainField $f) => sprintf('public %s $%s,', $f->phpType(), $f->property()), $this->fields),
        ), 8);
        $replacements['{{ dataDtoFromEntity }}'] = $this->indentLines(array_merge(
            $this->tenantScoped ? [sprintf('businessId: $%s->businessId,', $variable)] : [],
            array_map(fn (DomainField $f) => sprintf('%s: $%s->%s(),', $f->property(), $variable, $f->property()), $this->fields),
        ), 12);

        $replacements['{{ mapperToEntity }}'] = $this->indentLines(array_merge(
            $this->tenantScoped ? ['businessId: $model->business_id,'] : [],
            array_map(fn (DomainField $f) => sprintf('%s: $model->%s,', $f->property(), $f->name), $this->fields),
        ), 12);
        $replacements['{{ mapperToAttributes }}'] = $this->indentLines(array_merge(
            $this->tenantScoped ? [sprintf("'business_id' => $%s->businessId,", $variable)] : [],
            array_map(fn (DomainField $f) => sprintf("'%s' => $%s->%s(),", $f->name, $variable, $f->property()), $this->fields),
        ), 12);

        $replacements['{{ requestRules }}'] = $this->indentLines(
            array_map(fn (DomainField $f) => $f->validationRulesLine(), $this->fields),
            12,
        );
        $replacements['{{ resourceFields }}'] = $this->indentLines(
            array_map(fn (DomainField $f) => sprintf("'%s' => %s,", $f->name, $f->resourceValue()), $this->fields),
            12,
        );
        $replacements['{{ controllerInputArgs }}'] = $this->indentLines(
            array_map(fn (DomainField $f) => sprintf('%s: %s,', $f->property(), $f->requestAccessor()), $this->fields),
            12,
        );
        $replacements['{{ controllerImports }}'] = $this->importBlock($this->dateImport());
        $replacements['{{ routeMiddleware }}'] = $this->tenantScoped
            ? "['api', 'auth:sanctum', 'business']"
            : "['api']";

        $useCaseImports = [];
        if ($unique !== null) {
            $useCaseImports[] = "App\\Domains\\{$domain}\\Exceptions\\{$entity}{$unique->studly()}AlreadyTaken";
        }
        if ($this->tenantScoped) {
            $useCaseImports[] = 'App\\Shared\\Contracts\\BusinessContext';
        }
        $replacements['{{ useCaseImports }}'] = $this->importBlock($useCaseImports);
        $replacements['{{ businessContextParam }}'] = $this->tenantScoped
            ? "        private readonly BusinessContext \$business,\n"
            : '';
        $replacements['{{ businessIdArg }}'] = $this->tenantScoped
            ? "            businessId: \$this->business->currentBusinessId(),\n"
            : '';
        $replacements['{{ useCaseCreateArgs }}'] = rtrim($this->indentLines(
            array_map(fn (DomainField $f) => sprintf('%s: $input->%s,', $f->property(), $f->property()), $this->fields),
            12,
        ), "\n");
        $replacements['{{ useCaseUniqueGuard }}'] = $unique === null ? '' : implode("\n", [
            sprintf('        if ($this->%s->existsBy%s($input->%s)) {', Str::camel(Str::plural($entity)), $unique->studly(), $unique->property()),
            sprintf('            throw %s%sAlreadyTaken::for($input->%s);', $entity, $unique->studly(), $unique->property()),
            '        }',
            '',
            '',
        ]);

        $replacements['{{ repositoryUniqueMethod }}'] = $unique === null ? '' : implode("\n", [
            '',
            sprintf('    public function existsBy%s(%s $%s): bool;', $unique->studly(), $unique->phpType(), $unique->property()),
            '',
        ]);
        $replacements['{{ repositoryUniqueImplementation }}'] = $unique === null ? '' : implode("\n", [
            '',
            sprintf('    public function existsBy%s(%s $%s): bool', $unique->studly(), $unique->phpType(), $unique->property()),
            '    {',
            sprintf("        return {$entity}Model::query()->where('%s', $%s)->exists();", $unique->name, $unique->property()),
            '    }',
        ]);

        return $replacements;
    }

    /** The field whose emptiness create() guards against. */
    private function guardField(): ?DomainField
    {
        foreach ($this->fields as $field) {
            if ($field->isTextual() && $field->isRequired()) {
                return $field;
            }
        }

        return null;
    }

    private function uniqueField(): ?DomainField
    {
        foreach ($this->fields as $field) {
            if ($field->isUnique()) {
                return $field;
            }
        }

        return null;
    }

    private function activeField(): ?DomainField
    {
        foreach ($this->fields as $field) {
            if ($field->name === 'active' && $field->type === 'boolean') {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function dateImport(): array
    {
        foreach ($this->fields as $field) {
            if ($field->needsDateImport()) {
                return ['DateTimeImmutable'];
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function exceptionImports(string $domain, ?DomainField $guard, ?DomainField $active): array
    {
        $entity = Str::studly(Str::singular($domain));
        $imports = [];

        if ($guard !== null) {
            $imports[] = "App\\Domains\\{$domain}\\Exceptions\\Invalid{$entity}{$guard->studly()}";
        }

        if ($active !== null) {
            $imports[] = "App\\Domains\\{$domain}\\Exceptions\\{$entity}AlreadyInactive";
        }

        return $imports;
    }

    /**
     * @param  array<int, string>  $classes
     */
    private function importBlock(array $classes): string
    {
        if ($classes === []) {
            return '';
        }

        sort($classes);

        return implode('', array_map(fn (string $class) => "use {$class};\n", $classes));
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function indentLines(array $lines, int $spaces): string
    {
        $pad = str_repeat(' ', $spaces);

        return implode("\n", array_map(
            fn (string $line) => $line === '' ? '' : $pad.$line,
            $lines,
        ));
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array<string, string>
     */
    private function domainFiles(array $replacements): array
    {
        $files = self::DOMAIN_FILES;

        if ($this->guardField() !== null) {
            $files['exception.invalid-field.stub'] = 'Exceptions/Invalid{{ entity }}'.$replacements['{{ guardField }}'].'.php';
        }

        if ($this->uniqueField() !== null) {
            $files['exception.field-taken.stub'] = 'Exceptions/{{ entity }}'.$replacements['{{ uniqueField }}'].'AlreadyTaken.php';
        }

        if ($this->activeField() !== null) {
            $files['exception.already-inactive.stub'] = 'Exceptions/{{ entity }}AlreadyInactive.php';
        }

        return $files;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function writeSharedKernel(Filesystem $files, array $replacements): void
    {
        foreach (self::SHARED_FILES as $stub => $target) {
            $path = app_path($target);

            // The shared kernel is identical for every domain: write it once.
            if ($files->exists($path)) {
                continue;
            }

            $this->putFile($files, $path, $this->render($files, "shared/{$stub}", $replacements));
        }

        $this->bindSharedKernel($files);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function writeDomainFiles(Filesystem $files, string $domainPath, array $replacements): void
    {
        foreach ($this->domainFiles($replacements) as $stub => $target) {
            $path = $domainPath.'/'.strtr($target, $replacements);

            $this->putFile($files, $path, $this->render($files, "domain/{$stub}", $replacements));
        }
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function writeMigration(Filesystem $files, string $table, array $replacements): void
    {
        $existing = $files->glob(database_path("migrations/*_create_{$table}_table.php"));
        $existing = $existing === false ? [] : $existing;

        if ($existing !== [] && ! $this->option('force')) {
            $this->components->twoColumnDetail(basename($existing[0]), '<fg=yellow>skipped</>');

            return;
        }

        // Reuse the existing filename on --force so no duplicate migration appears.
        $path = $existing[0] ?? database_path('migrations/'.date('Y_m_d_His')."_create_{$table}_table.php");

        $this->putFile($files, $path, $this->render($files, 'domain/migration.stub', $replacements));
    }

    private function registerProvider(Filesystem $files, string $domain): void
    {
        $path = base_path('bootstrap/providers.php');
        $contents = $files->get($path);
        $class = "App\\Domains\\{$domain}\\{$domain}ServiceProvider::class";

        if (str_contains($contents, $class)) {
            $this->components->twoColumnDetail('bootstrap/providers.php', '<fg=yellow>already registered</>');

            return;
        }

        // Append as the last entry of the returned array.
        $updated = preg_replace('/\n\];/', "\n    {$class},\n];", $contents, 1);

        if ($updated === null || $updated === $contents) {
            $this->components->warn("Could not register the provider automatically. Add {$class} to bootstrap/providers.php.");

            return;
        }

        $files->put($path, $updated);
        $this->touched[] = $path;
        $this->components->twoColumnDetail('bootstrap/providers.php', '<fg=green>updated</>');
    }

    private function bindSharedKernel(Filesystem $files): void
    {
        $path = app_path('Providers/AppServiceProvider.php');
        $contents = $files->get($path);

        $imports = '';
        $bindings = '';

        // Check each binding separately: a single missing adapter must still
        // get wired, even when the others are already bound.
        foreach (self::SHARED_BINDINGS as $port => $adapter) {
            if (str_contains($contents, "bind({$port}::class")) {
                continue;
            }

            $imports .= "use App\\Shared\\Contracts\\{$port};\n";
            $imports .= "use App\\Shared\\Infrastructure\\{$adapter};\n";
            $bindings .= "        \$this->app->bind({$port}::class, {$adapter}::class);\n";
        }

        if ($bindings === '') {
            return;
        }

        $updated = str_replace(
            "use Illuminate\\Support\\ServiceProvider;\n",
            $imports."use Illuminate\\Support\\ServiceProvider;\n",
            $contents,
        );

        $updated = preg_replace(
            '/(public function register\(\): void\n    \{\n)(        \/\/\n)?/',
            '$1'.$bindings,
            $updated,
            1,
        );

        if ($updated === null || $updated === $contents) {
            $this->components->warn('Could not bind the shared kernel automatically. Bind Clock, IdGenerator and TransactionManager in AppServiceProvider.');

            return;
        }

        $files->put($path, $updated);
        $this->touched[] = $path;
        $this->components->twoColumnDetail('app/Providers/AppServiceProvider.php', '<fg=green>shared bindings added</>');
    }

    private function warnAboutLegacyFolders(Filesystem $files, string $domainPath, string $domain): void
    {
        foreach (self::LEGACY_FOLDERS as $folder) {
            if (! $files->isDirectory($domainPath.'/'.$folder)) {
                continue;
            }

            $this->components->warn(
                "app/Domains/{$domain}/{$folder}/ predates the layered layout and is superseded by Application/{$folder}/. It was left untouched; remove it once it is empty."
            );
        }
    }

    /** A tenant-scoped table references businesses.uuid, so that migration must run first. */
    private function warnAboutMigrationOrder(Filesystem $files, string $table): void
    {
        if (! $this->tenantScoped) {
            return;
        }

        $businesses = $files->glob(database_path('migrations/*_create_businesses_table.php')) ?: [];
        $own = $files->glob(database_path("migrations/*_create_{$table}_table.php")) ?: [];

        if ($businesses === []) {
            $this->components->warn(
                'No businesses migration exists yet. Run "make:domain Businesses --root" first, or this table\'s foreign key will fail to migrate.'
            );

            return;
        }

        if ($own !== [] && basename($businesses[0]) > basename($own[0])) {
            $this->components->warn(sprintf(
                'Migration order problem: %s runs after %s but is referenced by it. Rename the businesses migration to an earlier timestamp.',
                basename($businesses[0]),
                basename($own[0]),
            ));
        }
    }

    /** Import order depends on the entity name, so no fixed stub ordering is correct for every domain. */
    private function format(): void
    {
        $pint = base_path('vendor/bin/pint');

        if ($this->touched === [] || ! is_file($pint)) {
            return;
        }

        $result = Process::path(base_path())->run([PHP_BINARY, $pint, ...$this->touched]);

        $this->components->twoColumnDetail(
            'pint',
            $result->successful() ? '<fg=green>formatted</>' : '<fg=yellow>skipped (pint failed)</>',
        );
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function render(Filesystem $files, string $stub, array $replacements): string
    {
        return strtr($files->get(base_path("stubs/{$stub}")), $replacements);
    }

    private function putFile(Filesystem $files, string $path, string $contents): void
    {
        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $contents);
        $this->touched[] = $path;

        $this->components->twoColumnDetail(
            Str::after($path, base_path().DIRECTORY_SEPARATOR),
            '<fg=green>created</>',
        );
    }

    private function hasPhpFiles(Filesystem $files, string $path): bool
    {
        if (! $files->isDirectory($path)) {
            return false;
        }

        return collect($files->allFiles($path))->contains(fn ($file) => $file->getExtension() === 'php');
    }
}
