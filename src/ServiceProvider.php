<?php

namespace StatamicRadPack\Runway;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Traits\Conditionable;
use Statamic\API\Middleware\Cache;
use Statamic\Facades\Blueprint;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\GraphQL;
use Statamic\Facades\Permission;
use Statamic\Facades\Search;
use Statamic\Http\Middleware\RequireStatamicPro;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;
use StatamicRadPack\Runway\Http\Controllers\ApiController;
use StatamicRadPack\Runway\Policies\ResourcePolicy;
use StatamicRadPack\Runway\Search\Provider as SearchProvider;
use StatamicRadPack\Runway\Search\Searchable;

class ServiceProvider extends AddonServiceProvider
{
    use Conditionable;

    protected $actions = [
        Actions\DeleteModel::class,
        Actions\DuplicateModel::class,
    ];

    protected $commands = [
        Console\Commands\GenerateBlueprint::class,
        Console\Commands\GenerateMigration::class,
        Console\Commands\ListResources::class,
        Console\Commands\MigrateBlueprints::class,
        Console\Commands\RebuildUriCache::class,
    ];

    protected $fieldtypes = [
        Fieldtypes\BelongsToFieldtype::class,
        Fieldtypes\HasManyFieldtype::class,
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $scopes = [
        Query\Scopes\Filters\Fields::class,
    ];

    protected $tags = [
        Tags\RunwayTag::class,
    ];

    protected $updateScripts = [
        UpdateScripts\ChangePermissionNames::class,
        UpdateScripts\MigrateBlueprints::class,
    ];

    protected $vite = [
        'publicDirectory' => 'dist',
        'input' => [
            'resources/js/cp.js',
        ],
    ];

    public function boot()
    {
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'runway');
        $this->mergeConfigFrom(__DIR__.'/../config/runway.php', 'runway');

        if (! config('runway.disable_migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->publishes([
            __DIR__.'/../config/runway.php' => config_path('runway.php'),
        ], 'runway-config');

        Statamic::booted(function () {
            Runway::discoverResources();

            $this
                ->registerRouteBindings()
                ->registerPermissions()
                ->registerPolicies()
                ->registerNavigation()
                ->registerBlueprints()
                ->registerSearchProvider()
                ->bootGraphQl()
                ->bootApi()
                ->bootModelEventListeners()
                ->bootDataRepository();
        });
    }

    protected function registerRouteBindings(): self
    {
        Route::bind('resource', function ($value) {
            if (! Statamic::isCpRoute()) {
                return $value;
            }

            return Runway::findResource($value);
        });

        return $this;
    }

    protected function registerPermissions(): self
    {
        Permission::group('runway', 'Runway', function () {
            foreach (Runway::allResources() as $resource) {
                Permission::register("view {$resource->handle()}", function ($permission) use ($resource) {
                    $permission
                        ->label($this->permissionLabel('view', $resource))
                        ->children([
                            Permission::make("edit {$resource->handle()}")
                                ->label($this->permissionLabel('edit', $resource))
                                ->children([
                                    Permission::make("create {$resource->handle()}")
                                        ->label($this->permissionLabel('create', $resource)),

                                    Permission::make("delete {$resource->handle()}")
                                        ->label($this->permissionLabel('delete', $resource)),
                                ]),
                        ]);
                });
            }
        });

        return $this;
    }

    protected function registerPolicies(): self
    {
        Gate::policy(Resource::class, ResourcePolicy::class);

        return $this;
    }

    protected function registerNavigation(): self
    {
        Nav::extend(function ($nav) {
            Runway::allResources()
                ->reject(fn ($resource) => $resource->hidden())
                ->each(function (Resource $resource) use (&$nav) {
                    $nav->create($resource->name())
                        ->section('Content')
                        ->icon($resource->cpIcon())
                        ->route('runway.index', ['resource' => $resource->handle()])
                        ->can('view', $resource);
                });
        });

        return $this;
    }

    protected function registerBlueprints(): self
    {
        try {
            Blueprint::addNamespace('runway', base_path('resources/blueprints/runway'));

            Runway::allResources()->each(fn (Resource $resource) => $resource->blueprint());
        } catch (QueryException $e) {
            // A QueryException will be thrown when using the Eloquent Driver, where the `blueprints` table is
            // yet to be migrated (for example: during a fresh install). We'll catch the exception here and
            // ignore it to prevent any errors during the `composer dump-autoload` command.

            Log::warning('Runway attempted to register its blueprint namespace. However, it seems the `blueprints` table has yet to be migrated.');
        }

        return $this;
    }

    protected function bootGraphQl(): self
    {
        Runway::allResources()
            ->each(function (Resource $resource) {
                $this->app->bind("runway_graphql_types_{$resource->handle()}", fn () => new \StatamicRadPack\Runway\GraphQL\ResourceType($resource));

                GraphQL::addType("runway_graphql_types_{$resource->handle()}");
            })
            ->filter
            ->graphqlEnabled()
            ->each(function (Resource $resource) {
                $this->app->bind("runway_graphql_queries_{$resource->handle()}_index", fn () => new \StatamicRadPack\Runway\GraphQL\ResourceIndexQuery($resource));

                $this->app->bind("runway_graphql_queries_{$resource->handle()}_show", fn () => new \StatamicRadPack\Runway\GraphQL\ResourceShowQuery($resource));

                GraphQL::addQuery("runway_graphql_queries_{$resource->handle()}_index");
                GraphQL::addQuery("runway_graphql_queries_{$resource->handle()}_show");
            });

        return $this;
    }

    protected function bootApi(): self
    {
        if (config('statamic.api.enabled')) {
            Route::middleware([
                RequireStatamicPro::class,
                Cache::class,
            ])->group(function () {
                Route::middleware(config('statamic.api.middleware'))
                    ->name('statamic.api.')
                    ->prefix(config('statamic.api.route'))
                    ->group(function () {
                        Route::name('runway.index')->get('runway/{resourceHandle}', [ApiController::class, 'index']);
                        Route::name('runway.show')->get('runway/{resourceHandle}/{model}', [ApiController::class, 'show']);
                    });
            });
        }

        return $this;
    }

    protected function registerSearchProvider(): self
    {
        SearchProvider::register();

        return $this;
    }

    protected function bootModelEventListeners(): self
    {
        Runway::allResources()
            ->map(fn ($resource) => get_class($resource->model()))
            ->each(function ($class) {
                Event::listen('eloquent.saved: '.$class, fn ($model) => Search::updateWithinIndexes(new Searchable($model)));
                Event::listen('eloquent.deleted: '.$class, fn ($model) => Search::deleteFromIndexes(new Searchable($model)));
            });

        return $this;
    }

    protected function bootDataRepository(): self
    {
        if (Runway::usesRouting()) {
            $this->app->get(\Statamic\Contracts\Data\DataRepository::class)
                ->setRepository('runway-resources', Routing\ResourceRoutingRepository::class);
        }

        return $this;
    }

    protected function permissionLabel($permission, $resource): string
    {
        $translationKey = "runway.permissions.{$permission}";

        $label = trans($translationKey, [
            'resource' => $resource->name(),
        ]);

        if ($label == $translationKey) {
            return match ($permission) {
                'view' => "View {$resource->name()}",
                'edit' => "Edit {$resource->name()}",
                'create' => "Create {$resource->name()}",
                'delete' => "Delete {$resource->name()}"
            };
        }

        return $label;
    }
}
