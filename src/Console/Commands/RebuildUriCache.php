<?php

namespace StatamicRadPack\Runway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Antlers;
use StatamicRadPack\Runway\Resource;
use StatamicRadPack\Runway\Routing\RunwayUri;
use StatamicRadPack\Runway\Runway;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\progress;

class RebuildUriCache extends Command
{
    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'runway:rebuild-uris
        { --force : Force rebuilding of the URI cache. }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Rebuild Runway's URI cache of resources.";

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
        if (! $this->option('force')) {
            $confirm = confirm(
                'You are about to rebuild your entire URI cache. This may take part of your site down while running. Are you sure you want to continue?'
            );

            if (! $confirm) {
                return;
            }
        }

        RunwayUri::all()->each->delete();

        Runway::allResources()
            ->each(function (Resource $resource) {
                if (! $resource->hasRouting()) {
                    $this->components->warn("Skipping {$resource->name()}, routing not configured.");

                    return;
                }

                $query = $resource->model()->newQuery()->withoutGlobalScopes();
                $query->when($query->hasNamedScope('runwayRoutes'), fn ($query) => $query->runwayRoutes());

                if (! $query->exists()) {
                    $this->components->warn("Skipping {$resource->name()}, no models to cache.");

                    return;
                }

                progress(
                    label: "Caching {$resource->name()} URIs",
                    steps: $query->get(),
                    callback: function ($model) use ($resource) {
                        $uri = Antlers::parser()
                            ->parse($resource->route(), $model->toAugmentedArray())
                            ->__toString();

                        $uri = Str::start($uri, '/');

                        $model->runwayUri()->create([
                            'uri' => $uri,
                        ]);
                    }
                );
            });
    }
}
