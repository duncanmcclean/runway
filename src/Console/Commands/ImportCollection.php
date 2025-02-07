<?php

namespace StatamicRadPack\Runway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;
use Statamic\Contracts\Entries\Collection;
use Statamic\Facades;
use Statamic\Facades\YAML;
use Statamic\Fields\Field;
use StatamicRadPack\Runway\Runway;
use Stillat\Proteus\Support\Facades\ConfigWriter;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;

class ImportCollection extends Command
{
    use RunsInPlease;

    private $collection;
    private $modelName;
    private $modelClass;
    private $blueprint;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'runway:import-collection
        { collection? : The handle of the collection to import. }
        { --force : Force overwrite if files already exist }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import a collection into the database.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->collection = $this->promptForCollection();

        $this->modelName = Str::of($this->collection->handle())->singular()->title();
        $this->modelClass = "App\Models\\{$this->modelName}";

        if (class_exists($this->modelClass) && ! $this->option('force')) {
            $this->components->error("A [{$this->modelName}] model already exists.");

            return 1;
        }

        $this
            ->copyBlueprint()
            ->createEloquentModel()
            ->appendToRunwayConfig()
            ->createDatabaseMigration()
            ->importEntries();

        $this->components->info("The {$this->collection->title()} collection has been imported.");

        $this->line('  Next steps:');

        $this->components->bulletList([
            'Replace the {{ collection }} tag with the {{ runway }} tag in your templates.',
            'If you have any Entry fields pointing to this collection, replace them with the Belong To or Has Many fieldtypes.',
            "When you're ready, delete the {$this->collection->title()} collection.",
        ]);
    }

    private function promptForCollection(): Collection
    {
        $handle = $this->argument('collection') ?? select(
            label: 'Which collection would you like to import?',
            options: Facades\Collection::all()
                ->mapWithKeys(fn (Collection $collection) => [$collection->handle() => $collection->title()])
                ->all(),
        );

        $collection = Facades\Collection::find($handle);

        if ($collection->entryBlueprints()->count() > 1) {
            if (! confirm("Runway doesn't support multiple blueprints. Only the first blueprint will be imported. Are you sure you want to continue?")) {
                exit;
            }
        }

        if ($collection->sites()->count() > 1) {
            if (! confirm("Runway doesn't support localization. Are you sure you want to continue?")) {
                exit;
            }
        }

        if ($collection->hasStructure()) {
            if (! confirm("Runway doesn't support trees. Are you sure you want to continue?")) {
                exit;
            }
        }

        return $collection;
    }

    private function copyBlueprint(): self
    {
        $contents = Str::of(YAML::dump($this->collection->entryBlueprint()->contents()))
            ->replace("- 'new \Statamic\Rules\UniqueEntryValue({collection}, {id}, {site})'", '')
            ->__toString();

        $this->blueprint = Facades\Blueprint::make("runway::{$this->collection->handle()}")
            ->setContents(YAML::parse($contents))
            ->removeField('parent');

        if ($this->collection->dated()) {
            $this->blueprint->ensureField('date', ['type' => 'date', 'display' => __('Date')], 'sidebar');
        }

        $this->blueprint->save();

        return $this;
    }

    private function createEloquentModel(): self
    {
        $modelContents = Str::of(File::get(__DIR__.'/stubs/model.stub'))
            ->replace('{{ namespace }}', 'App\Models')
            ->replace('{{ class }}', $this->modelName)
            ->replace('{{ traits }}', $this->collection->routes()->isNotEmpty() ? 'HasRunwayResource, RunwayRoutes' : 'HasRunwayResource')
            ->replace('{{ fillable }}', collect($this->getDatabaseColumns())
                ->pluck('name')
                ->filter()
                ->map(fn ($column) => "'{$column}'")
                ->join(', '))
            ->replace('{{ casts }}', collect($this->getDatabaseColumns())
                ->filter(fn ($column) => in_array($column['type'], ['json', 'boolean', 'datetime', 'date', 'time', 'float', 'integer']))
                ->map(fn ($column) => "            '{$column['name']}' => '{$column['type']}',")
                ->join(PHP_EOL))
            ->__toString();

        File::put(app_path("Models/{$this->modelName}.php"), $modelContents);

        spl_autoload('App\Models\\'.$this->modelName);

        return $this;
    }

    private function appendToRunwayConfig(): self
    {
        ConfigWriter::write(
            'runway.resources.'.str_replace('\\', '\\', $this->modelClass),
            array_filter([
                'handle' => $this->collection->handle(),
                'name' => $this->collection->title(),
                'published' => true,
                'revisions' => $this->collection->revisionsEnabled(),
                'route' => $this->collection->route(Facades\Site::default()->handle()),
                'template' => $this->collection->template(),
                'layout' => $this->collection->layout(),
                'order_by' => $this->collection->sortField(),
                'order_by_direction' => $this->collection->sortDirection(),
            ]),
        );

        // Ensure the array key is "ModelName::class", and not "'\App\Models\ModelName'".
        $contents = File::get(config_path('runway.php'));

        $contents = Str::of($contents)
            ->replace("'App\\Models\\{$this->modelName}' => [", "{$this->modelName}::class => [")
            ->when(! Str::contains($contents, "use App\Models\\{$this->modelName};"), fn ($contents) => $contents
                ->replace('return [', 'use App\Models\\'.$this->modelName.';'.PHP_EOL.PHP_EOL.'return [')
            )
            ->__toString();

        File::put(config_path('runway.php'), $contents);

        Config::set('runway', require config_path('runway.php'));

        Runway::discoverResources();

        return $this;
    }

    private function createDatabaseMigration(): self
    {
        /** @var Model $model */
        $model = new $this->modelClass;

        if (Schema::hasTable($model->getTable())) {
            $this->components->warn("The [{$model->getTable()}] table already exists. Skipping generation of migration.");

            return $this;
        }

        $migrationContents = Str::of(File::get(__DIR__.'/stubs/migration.stub'))
            ->replace('{{ table }}', $model->getTable())
            ->replace('{{ columns }}', collect($this->getDatabaseColumns())->map(function (array $column) {
                $type = $column['type'];

                $string = "\$table->{$type}";

                isset($column['name'])
                    ? $string = "{$string}('{$column['name']}')"
                    : $string = "{$string}()";

                if (isset($column['nullable'])) {
                    $string = "{$string}->nullable()";
                }

                if (isset($column['default'])) {
                    $default = $column['default'];

                    if (is_string($default)) {
                        $default = "'{$default}'";
                    }

                    if (is_bool($default)) {
                        $default = $default ? 'true' : 'false';
                    }

                    $string = "{$string}->default({$default})";
                }

                return "            {$string};";
            })->implode(PHP_EOL))
            ->__toString();

        File::put(database_path('migrations/'.date('Y_m_d_His')."_create_{$model->getTable()}_table.php"), $migrationContents);

        if (confirm('Would you like to run the migration?')) {
            $this->call('migrate');
        }

        return $this;
    }

    private function importEntries(): self
    {
        /** @var Model $model */
        $model = new $this->modelClass;

        if (! Schema::hasTable($model->getTable())) {
            return $this;
        }

        if (! confirm('Would you like to import existing entries')) {
            return $this;
        }

        $this->newLine();

        $progress = progress(label: 'Importing entries', steps: $this->collection->queryEntries()->count());
        $progress->start();

        $this->collection->queryEntries()->chunk(100, function ($entries) use ($model, $progress) {
            $entries->each(function ($entry) use ($model, $progress) {
                $attributes = $entry->data()
                    ->only($this->blueprint->fields()->all()->map->handle())
                    ->merge([
                        'uuid' => $entry->id(),
                        'slug' => $entry->slug(),
                        'date' => $entry->date(),
                        'published' => $entry->published(),
                        'updated_at' => $entry->get('updated_at') ?? now(),
                    ])
                    ->all();

                $model = $model::find($entry->id()) ?? (new $model);
                $model->forceFill($attributes)->save();

                $progress->advance();
            });
        });

        $progress->finish();

        return $this;
    }

    private function getDatabaseColumns(): array
    {
        return $this->blueprint->fields()->all()
            ->map(function (Field $field) {
                return [
                    'type' => $this->getColumnTypeForField($field),
                    'name' => $field->handle(),
                    'nullable' => ! $field->isRequired(),
                ];
            })
            ->prepend(['type' => 'uuid'])
            ->push(['type' => 'boolean', 'name' => 'published', 'default' => false])
            ->push(['type' => 'timestamps'])
            ->values()
            ->all();
    }

    private function getColumnTypeForField(Field $field): string
    {
        if (in_array($field->type(), ['array', 'checkboxes', 'grid', 'group', 'list', 'replicator', 'table'])) {
            return 'json';
        }

        if (
            $field->get('max_items') &&
            in_array($field->type(), ['assets', 'asset_container', 'asset_folder', 'collections', 'dictionary', 'entries', 'navs', 'select', 'sites', 'structures', 'taggable', 'taxonomies', 'terms', 'user_groups', 'user_roles', 'users'])
        ) {
            return 'json';
        }

        if (in_array($field->type(), ['html', 'markdown', 'textarea'])) {
            return 'text';
        }

        if ($field->type() === 'bard') {
            return $field->get('save_html')
                ? 'text'
                : 'json';
        }

        if ($field->type() === 'code') {
            return $field->get('mode_selectable')
                ? 'json'
                : 'text';
        }

        if ($field->type() === 'date') {
            return $field->get('time_enabled')
                ? 'datetime'
                : 'date';
        }

        if ($field->type() === 'time') {
            return 'time';
        }

        if ($field->type() === 'float') {
            return 'float';
        }

        if ($field->type() === 'integer') {
            return 'integer';
        }

        if ($field->type() === 'boolean') {
            return 'boolean';
        }

        return 'string';
    }
}
