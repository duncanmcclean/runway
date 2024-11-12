<?php

namespace StatamicRadPack\Runway\Tests\Http\Controllers;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Config;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Post;

class ApiControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Config::set('statamic.api.resources.runway', [
            'posts' => ['allowed_filters' => ['title']],
        ]);
    }

    #[Test]
    public function gets_a_resource_that_exists()
    {
        $posts = Post::factory()->count(2)->create();

        $this
            ->get(route('statamic.api.runway.index', ['resourceHandle' => 'posts']))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('data.0.id', $posts[0]->id)
            ->assertJsonPath('data.1.id', $posts[1]->id);
    }

    #[Test]
    public function returns_not_found_on_a_resource_that_doesnt_exist()
    {
        Post::factory()->count(2)->create();

        $this
            ->get(route('statamic.api.runway.index', ['resourceHandle' => 'posts2']))
            ->assertNotFound();
    }

    #[Test]
    public function it_filters_out_unpublished_models()
    {
        $posts = Post::factory()->count(2)->create();
        Post::factory()->count(2)->unpublished()->create();

        $this
            ->get(route('statamic.api.runway.index', ['resourceHandle' => 'posts']))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $posts[0]->id)
            ->assertJsonPath('data.1.id', $posts[1]->id);
    }

    #[Test]
    public function paginates_a_resource_list()
    {
        Post::factory()->count(10)->create();

        $this
            ->get(route('statamic.api.runway.index', ['resourceHandle' => 'posts', 'limit' => 5]))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 10);

        $this
            ->get(route('statamic.api.runway.index', ['resourceHandle' => 'posts', 'limit' => 5, 'page' => 2]))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 10);
    }

    #[Test]
    public function filters_a_resource_list()
    {
        [$postA, $postB, $postC] = Post::factory()->count(3)->create();

        $postA->update(['title' => 'Test One']);
        $postB->update(['title' => 'Test Two']);
        $postC->update(['title' => 'Test Three']);

        $this
            ->get(route('statamic.api.runway.index', ['resourceHandle' => 'posts']))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 3);

        $this
            ->get(route('statamic.api.runway.index', ['resourceHandle' => 'posts', 'filter[title:contains]' => 'one']))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 1);

        $this
            ->get(route('statamic.api.runway.index', ['resourceHandle' => 'posts', 'filter[title:contains]' => 'test']))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    public function wont_filter_a_resource_list_on_a_forbidden_filter()
    {
        [$postA, $postB, $postC] = Post::factory()->count(3)->create();

        $postA->update(['title' => 'Test One']);
        $postB->update(['title' => 'Test Two']);
        $postC->update(['title' => 'Test Three']);

        $this
            ->get(route('statamic.api.runway.index', ['resourceHandle' => 'posts', 'filter[slug:contains]' => 'one']))
            ->assertStatus(422);
    }

    #[Test]
    public function gets_a_resource_model_that_exists()
    {
        $post = Post::factory()->create();

        $this
            ->get(route('statamic.api.runway.show', ['resourceHandle' => 'posts', 'model' => $post->id]))
            ->assertOk()
            ->assertSee(['data'])
            ->assertJsonPath('data.id', $post->id)
            ->assertJsonPath('data.title', $post->title);
    }

    #[Test]
    public function gets_a_resource_model_with_nested_fields()
    {
        $post = Post::factory()->create([
            'values' => [
                'alt_title' => 'Alternative Title...',
                'alt_body' => 'This is a **great** post! You should *read* it.',
            ],
        ]);

        $this
            ->get(route('statamic.api.runway.show', ['resourceHandle' => 'posts', 'model' => $post->id]))
            ->assertOk()
            ->assertSee(['data'])
            ->assertJsonPath('data.id', $post->id)
            ->assertJsonPath('data.values.alt_title', 'Alternative Title...')
            ->assertJsonPath('data.values.alt_body', '<p>This is a <strong>great</strong> post! You should <em>read</em> it.</p>
');
    }

    #[Test]
    public function gets_a_resource_model_with_belongs_to_relationship()
    {
        $post = Post::factory()->create();

        $this
            ->get(route('statamic.api.runway.show', ['resourceHandle' => 'posts', 'model' => $post->id]))
            ->assertOk()
            ->assertSee(['data'])
            ->assertJsonPath('data.id', $post->id)
            ->assertJsonPath('data.author_id.id', $post->author->id)
            ->assertJsonPath('data.author_id.name', $post->author->name);
    }

    #[Test]
    public function returns_not_found_on_a_model_that_does_not_exist()
    {
        $this
            ->get(route('statamic.api.runway.show', ['resourceHandle' => 'posts', 'model' => 44]))
            ->assertNotFound();
    }

    #[Test]
    public function it_doesnt_return_unpublished_model()
    {
        $post = Post::factory()->unpublished()->create();

        $this
            ->get(route('statamic.api.runway.show', ['resourceHandle' => 'posts', 'model' => $post->id]))
            ->assertNotFound();
    }
}
