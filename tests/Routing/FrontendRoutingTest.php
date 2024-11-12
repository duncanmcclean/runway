<?php

namespace StatamicRadPack\Runway\Tests\Routing;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use StatamicRadPack\Runway\Runway;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Post;

class FrontendRoutingTest extends TestCase
{
    #[Test]
    public function returns_resource_response_for_resource()
    {
        $post = Post::factory()->create();
        $runwayUri = $post->fresh()->runwayUri;

        $this
            ->get($runwayUri->uri)
            ->assertOk()
            ->assertSee($post->title)
            ->assertSee('TEMPLATE: default')
            ->assertSee('LAYOUT: layout');
    }

    #[Test]
    public function returns_resource_response_for_resource_with_nested_field()
    {
        $post = Post::factory()->create([
            'values' => [
                'alt_title' => 'Alternative Title...',
            ],
        ]);

        $runwayUri = $post->fresh()->runwayUri;

        $this
            ->get($runwayUri->uri)
            ->assertOk()
            ->assertSee('Alternative Title...')
            ->assertSee('TEMPLATE: default')
            ->assertSee('LAYOUT: layout');
    }

    #[Test]
    public function returns_resource_response_for_resource_with_custom_template()
    {
        Config::set('runway.resources.'.Post::class.'.template', 'custom');

        Runway::discoverResources();

        $post = Post::factory()->create();
        $runwayUri = $post->fresh()->runwayUri;

        $this
            ->get($runwayUri->uri)
            ->assertOk()
            ->assertSee($post->title)
            ->assertSee('TEMPLATE: custom')
            ->assertSee('LAYOUT: layout');
    }

    #[Test]
    public function returns_resource_response_for_resource_with_custom_layout()
    {
        Config::set('runway.resources.'.Post::class.'.layout', 'blog-layout');

        Runway::discoverResources();

        $post = Post::factory()->create();
        $runwayUri = $post->fresh()->runwayUri;

        $this
            ->get($runwayUri->uri)
            ->assertOk()
            ->assertSee($post->title)
            ->assertSee('TEMPLATE: default')
            ->assertSee('LAYOUT: blog-layout');
    }
}
