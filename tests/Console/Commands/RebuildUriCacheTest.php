<?php

namespace StatamicRadPack\Runway\Tests\Console\Commands;

use PHPUnit\Framework\Attributes\Test;
use StatamicRadPack\Runway\Routing\RunwayUri;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Author;
use Workbench\App\Models\Post;

class RebuildUriCacheTest extends TestCase
{
    #[Test]
    public function it_rebuilds_the_uri_cache()
    {
        Post::factory()->count(5)->createQuietly();

        $this
            ->artisan('runway:rebuild-uris')
            ->expectsConfirmation(
                'You are about to rebuild your entire URI cache. This may take part of your site down while running. Are you sure you want to continue?',
                'yes'
            );

        $this->assertCount(5, RunwayUri::all());
    }

    #[Test]
    public function can_build_uri_with_antlers()
    {
        Post::factory()->createQuietly(['slug' => 'hello-world']);

        $this
            ->artisan('runway:rebuild-uris')
            ->expectsConfirmation(
                'You are about to rebuild your entire URI cache. This may take part of your site down while running. Are you sure you want to continue?',
                'yes'
            );

        $this->assertCount(1, RunwayUri::all());
        $this->assertEquals('/posts/hello-world', RunwayUri::first()->uri);
    }

    #[Test]
    public function does_not_rebuild_uri_cache_when_no_confirmation_is_provided()
    {
        Post::factory()->count(5)->create(); // `create` will trigger the URIs to be built via model events.

        $this
            ->artisan('runway:rebuild-uris')
            ->expectsConfirmation(
                'You are about to rebuild your entire URI cache. This may take part of your site down while running. Are you sure you want to continue?',
                'no'
            );

        $this->assertCount(5, RunwayUri::all());
    }

    #[Test]
    public function skips_resources_without_routing_configured()
    {
        Author::factory()->count(3)->createQuietly();

        $this
            ->artisan('runway:rebuild-uris')
            ->expectsConfirmation(
                'You are about to rebuild your entire URI cache. This may take part of your site down while running. Are you sure you want to continue?',
                'yes'
            )
            ->expectsOutputToContain('Skipping Authors, routing not configured.');

        $this->assertCount(0, RunwayUri::all());
    }
}
