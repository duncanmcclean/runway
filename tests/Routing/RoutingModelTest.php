<?php

namespace StatamicRadPack\Runway\Tests\Routing;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use StatamicRadPack\Runway\Routing\RoutingModel;
use StatamicRadPack\Runway\Runway;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Post;

class RoutingModelTest extends TestCase
{
    #[Test]
    public function can_get_route()
    {
        $post = Post::factory()->createQuietly();
        $post->runwayUri()->create(['uri' => '/blog/post-slug']);

        $routingModel = new RoutingModel($post);

        $this->assertEquals('/blog/post-slug', $routingModel->route());
    }

    #[Test]
    public function cant_get_route_without_runway_uri()
    {
        $post = Post::factory()->createQuietly();

        $routingModel = new RoutingModel($post);

        $this->assertNull($routingModel->route());
    }

    #[Test]
    public function can_get_route_data()
    {
        $post = Post::factory()->createQuietly();
        $post->runwayUri()->create(['uri' => '/blog/post-slug']);

        $routingModel = new RoutingModel($post);

        $this->assertEquals([
            'id' => $post->id,
        ], $routingModel->routeData());
    }

    #[Test]
    public function can_get_uri()
    {
        $post = Post::factory()->createQuietly();
        $post->runwayUri()->create(['uri' => '/blog/post-slug']);

        $routingModel = new RoutingModel($post);

        $this->assertEquals('/blog/post-slug', $routingModel->uri());
    }

    #[Test]
    public function can_get_url_without_redirect()
    {
        $post = Post::factory()->createQuietly();
        $post->runwayUri()->create(['uri' => '/blog/post-slug']);

        $routingModel = new RoutingModel($post);

        $this->assertEquals('/blog/post-slug', $routingModel->urlWithoutRedirect());
    }

    #[Test]
    public function can_get_response()
    {
        $post = Post::factory()->createQuietly();
        $post->runwayUri()->create(['uri' => '/blog/post-slug']);

        $routingModel = new RoutingModel($post);

        $response = $routingModel->toResponse(new Request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString("<h1>{$post->title}</h1>", $response->getContent());
        $this->assertStringContainsString("<article>{$post->body}</article>", $response->getContent());
    }

    #[Test]
    public function can_get_template()
    {
        Config::set('runway.resources.Workbench\App\Models\Post.template', 'posts.show');
        Runway::discoverResources();

        $post = Post::factory()->createQuietly();
        $post->runwayUri()->create(['uri' => '/blog/post-slug']);

        $routingModel = new RoutingModel($post);

        $this->assertEquals('posts.show', $routingModel->template());
    }

    #[Test]
    public function can_get_layout()
    {
        Config::set('runway.resources.Workbench\App\Models\Post.layout', 'layouts.post');
        Runway::discoverResources();

        $post = Post::factory()->createQuietly();
        $post->runwayUri()->create(['uri' => '/blog/post-slug']);

        $routingModel = new RoutingModel($post);

        $this->assertEquals('layouts.post', $routingModel->layout());
    }

    #[Test]
    public function can_get_id()
    {
        $post = Post::factory()->createQuietly();
        $post->runwayUri()->create(['uri' => '/blog/post-slug']);

        $routingModel = new RoutingModel($post);

        $this->assertEquals($post->id, $routingModel->id());
    }

    #[Test]
    public function can_get_route_key()
    {
        $post = Post::factory()->createQuietly();
        $post->runwayUri()->create(['uri' => '/blog/post-slug']);

        $routingModel = new RoutingModel($post);

        $this->assertEquals($post->id, $routingModel->getRouteKey());
    }

    #[Test]
    public function can_get_augmented_array_data()
    {
        $post = Post::factory()->createQuietly();
        $post->runwayUri()->create(['uri' => '/blog/post-slug']);

        $routingModel = new RoutingModel($post);

        $this->assertEquals($post->toArray(), $routingModel->augmentedArrayData());
    }

    #[Test]
    public function can_get_something_from_model()
    {
        $post = Post::factory()->createQuietly();
        $post->runwayUri()->create(['uri' => '/blog/post-slug']);

        $routingModel = new RoutingModel($post);

        $this->assertEquals($post->slug, $routingModel->slug);
    }
}
