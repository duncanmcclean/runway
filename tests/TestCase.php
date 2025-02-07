<?php

namespace StatamicRadPack\Runway\Tests;

use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Statamic\Facades\Blueprint;
use Statamic\Stache\Stores\UsersStore;
use Statamic\Statamic;
use Statamic\Testing\AddonTestCase;
use StatamicRadPack\Runway\ServiceProvider;

abstract class TestCase extends AddonTestCase
{
    use RefreshDatabase, WithWorkbench;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected $shouldFakeVersion = true;

    protected function setUp(): void
    {
        parent::setUp();

        //        $this->loadMigrationsFrom(__DIR__.'/__fixtures__/database/migrations');
        //        $this->runLaravelMigrations();
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(
            Encrypter::generateKey($app['config']['app.cipher'])
        ));

        $app['config']->set('statamic.api.enabled', true);
        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.users.repository', 'file');

        $app['config']->set('statamic.stache.stores.users', [
            'class' => UsersStore::class,
            'directory' => __DIR__.'/__fixtures__/users',
        ]);

        $app['config']->set('runway', require (__DIR__.'/__fixtures__/config/runway.php'));

        Statamic::booted(function () {
            Blueprint::setDirectory(__DIR__.'/__fixtures__/resources/blueprints');
        });
    }
}
