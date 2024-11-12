<?php

namespace StatamicRadPack\Runway\Tests\Tags;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Value;
use StatamicRadPack\Runway\Runway;
use StatamicRadPack\Runway\Tags\RunwayTag;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Author;
use Workbench\App\Models\Post;

class RunwayTagTest extends TestCase
{
    public $tag;

    public function setUp(): void
    {
        parent::setUp();

        $this->tag = resolve(RunwayTag::class)
            ->setParser(Antlers::parser())
            ->setContext([]);
    }

    #[Test]
    public function has_been_registered()
    {
        $this->assertTrue(isset(app()['statamic.tags']['runway']));
    }

    #[Test]
    public function can_get_models_with_no_parameters()
    {
        $posts = Post::factory()->count(5)->create();

        $this->tag->setParameters([]);
        $usage = $this->tag->wildcard('post');

        $this->assertEquals(5, count($usage));

        $this->assertEquals((string) $usage[0]['title'], $posts[0]->title);
        $this->assertEquals((string) $usage[1]['title'], $posts[1]->title);
        $this->assertEquals((string) $usage[2]['title'], $posts[2]->title);
        $this->assertEquals((string) $usage[3]['title'], $posts[3]->title);
        $this->assertEquals((string) $usage[4]['title'], $posts[4]->title);
    }

    #[Test]
    public function can_get_models_with_select_parameter()
    {
        $posts = Post::factory()->count(5)->create();

        $this->tag->setParameters([
            'select' => 'id,title,slug',
        ]);

        $usage = $this->tag->wildcard('post');

        $this->assertEquals(5, count($usage));

        $this->assertEquals((string) $usage[0]['title']->value(), $posts[0]->title);
        $this->assertEquals((string) $usage[0]['slug']->value(), $posts[0]->slug);
        $this->assertEmpty((string) $usage[0]['body']->value());

        $this->assertEquals((string) $usage[1]['title']->value(), $posts[1]->title);
        $this->assertEquals((string) $usage[1]['slug']->value(), $posts[1]->slug);
        $this->assertEmpty((string) $usage[1]['body']->value());

        $this->assertEquals((string) $usage[2]['title']->value(), $posts[2]->title);
        $this->assertEquals((string) $usage[2]['slug']->value(), $posts[2]->slug);
        $this->assertEmpty((string) $usage[2]['body']->value());

        $this->assertEquals((string) $usage[3]['title']->value(), $posts[3]->title);
        $this->assertEquals((string) $usage[3]['slug']->value(), $posts[3]->slug);
        $this->assertEmpty((string) $usage[3]['body']->value());

        $this->assertEquals((string) $usage[4]['title']->value(), $posts[4]->title);
        $this->assertEquals((string) $usage[4]['slug']->value(), $posts[4]->slug);
        $this->assertEmpty((string) $usage[4]['body']->value());
    }

    #[Test]
    public function can_get_models_with_scope_parameter()
    {
        $posts = Post::factory()->count(5)->create();

        $posts[0]->update(['title' => 'Pasta']);
        $posts[2]->update(['title' => 'Apple']);
        $posts[4]->update(['title' => 'Burger']);

        $this->tag->setParameters([
            'scope' => 'food',
        ]);

        $usage = $this->tag->wildcard('post');

        $this->assertEquals(3, count($usage));
        $this->assertEquals((string) $usage[0]['title']->value(), 'Pasta');
        $this->assertEquals((string) $usage[1]['title']->value(), 'Apple');
        $this->assertEquals((string) $usage[2]['title']->value(), 'Burger');
    }

    #[Test]
    public function can_get_models_with_scope_parameter_and_scope_arguments()
    {
        $posts = Post::factory()->count(5)->create();

        $posts[0]->update(['title' => 'Pasta']);
        $posts[2]->update(['title' => 'Apple']);
        $posts[4]->update(['title' => 'Burger']);

        $this->tag->setContext([
            'fab' => 'idoo',
        ]);

        $this->tag->setParameters([
            'scope' => 'fruit:fab',
        ]);

        $usage = $this->tag->wildcard('post');

        $this->assertEquals(1, count($usage));
        $this->assertEquals((string) $usage[0]['title']->value(), 'Apple');
    }

    #[Test]
    public function can_get_models_with_scope_parameter_and_scope_arguments_and_multiple_scopes()
    {
        $posts = Post::factory()->count(5)->create();

        $posts[0]->update(['title' => 'Pasta']);
        $posts[2]->update(['title' => 'Apple']);
        $posts[4]->update(['title' => 'Burger']);

        $this->tag->setContext([
            'fab' => 'idoo',
        ]);

        $this->tag->setParameters([
            'scope' => 'food|fruit:fab',
        ]);

        $usage = $this->tag->wildcard('post');

        $this->assertEquals(1, count($usage));
        $this->assertEquals((string) $usage[0]['title']->value(), 'Apple');
    }

    #[Test]
    public function can_get_models_with_where_parameter()
    {
        $posts = Post::factory()->count(5)->create();

        $posts[0]->update(['title' => 'penguin']);

        $this->tag->setParameters([
            'where' => 'title:penguin',
        ]);

        $usage = $this->tag->wildcard('post');

        $this->assertEquals(1, count($usage));
        $this->assertEquals((string) $usage[0]['title']->value(), 'penguin');
    }

    #[Test]
    public function can_get_models_with_where_parameter_when_condition_is_on_relationship_field()
    {
        $posts = Post::factory()->count(5)->create();
        $author = Author::factory()->create();

        $posts[0]->update(['author_id' => $author->id]);
        $posts[2]->update(['author_id' => $author->id]);
        $posts[3]->update(['author_id' => $author->id]);

        $this->tag->setParameters([
            'where' => 'author_id:'.$author->id,
        ]);

        $usage = $this->tag->wildcard('post');

        $this->assertEquals(3, count($usage));
        $this->assertEquals((string) $usage[0]['title']->value(), $posts[0]->title);
        $this->assertEquals((string) $usage[1]['title']->value(), $posts[2]->title);
        $this->assertEquals((string) $usage[2]['title']->value(), $posts[3]->title);
    }

    #[Test]
    public function can_get_models_with_with_parameter()
    {
        $posts = Post::factory()->count(5)->create();

        $posts[0]->update(['title' => 'tiger']);

        $this->tag->setParameters([
            'with' => 'author',
        ]);

        $usage = $this->tag->wildcard('post');

        $this->assertEquals(5, count($usage));
        $this->assertEquals((string) $usage[0]['title'], 'tiger');

        $this->assertInstanceOf(Value::class, $usage[0]['author']);
        $this->assertEquals($usage[0]['author']->value()['name']->value(), $posts[0]->author->name);
    }

    #[Test]
    public function can_get_models_with_sort_parameter()
    {
        $posts = Post::factory()->count(2)->create();

        $posts[0]->update(['title' => 'abc']);
        $posts[1]->update(['title' => 'def']);

        $this->tag->setParameters([
            'sort' => 'title:desc',
        ]);

        $usage = $this->tag->wildcard('post');

        $this->assertEquals(2, count($usage));

        $this->assertEquals((string) $usage[0]['title'], 'def');
        $this->assertEquals((string) $usage[1]['title'], 'abc');
    }

    #[Test]
    public function can_get_models_with_scoping()
    {
        $posts = Post::factory()->count(2)->create();

        $posts[0]->update(['title' => 'abc']);
        $posts[1]->update(['title' => 'def']);

        $this->tag->setParameters([
            'as' => 'items',
        ]);

        $usage = $this->tag->wildcard('post');

        $this->assertEquals(2, count($usage['items']));

        $this->assertEquals((string) $usage['items'][0]['title'], 'abc');
        $this->assertEquals((string) $usage['items'][1]['title'], 'def');
    }

    #[Test]
    public function can_get_models_with_limit_parameter()
    {
        $posts = Post::factory()->count(5)->create();

        $this->tag->setParameters([
            'limit' => 2,
        ]);

        $usage = $this->tag->wildcard('post');

        $this->assertEquals(2, count($usage));

        $this->assertEquals((string) $usage[0]['title'], $posts[0]['title']);
        $this->assertEquals((string) $usage[1]['title'], $posts[1]['title']);

        $this->assertFalse(isset($usage[2]));
    }

    #[Test]
    public function can_get_models_with_scoping_and_pagination()
    {
        $posts = Post::factory()->count(5)->create();

        $this->tag->setParameters([
            'limit' => 2,
            'as' => 'items',
        ]);

        $usage = $this->tag->wildcard('post');

        $this->assertEquals(2, count($usage['items']));

        $this->assertEquals((string) $usage['items'][0]['title'], $posts[0]['title']);
        $this->assertEquals((string) $usage['items'][1]['title'], $posts[1]['title']);

        $this->assertFalse(isset($usage['items'][2]));

        $this->assertArrayHasKey('paginate', $usage);
        $this->assertArrayHasKey('no_results', $usage);
    }

    #[Test]
    public function can_get_models_and_non_blueprint_columns_are_returned()
    {
        $posts = Post::factory()->count(2)->create();

        $this->tag->setParameters([]);

        $usage = $this->tag->wildcard('post');

        $this->assertEquals(2, count($usage));

        $this->assertEquals($usage[0]['id']->value(), $posts[0]['id']);
        $this->assertEquals($usage[1]['id']->value(), $posts[1]['id']);

        $this->assertEquals((string) $usage[0]['title']->value(), $posts[0]['title']);
        $this->assertEquals((string) $usage[1]['title']->value(), $posts[1]['title']);
    }

    #[Test]
    public function can_get_models_with_studly_case_resource_handle()
    {
        $postBlueprint = Blueprint::find('runway::post');
        Blueprint::shouldReceive('find')->with('runway::BlogPosts')->andReturn($postBlueprint);

        Config::set('runway.resources.'.Post::class.'.handle', 'BlogPosts');

        Runway::discoverResources();

        $posts = Post::factory()->count(5)->create();

        $this->tag->setParameters([]);
        $usage = $this->tag->wildcard('blog_posts');

        $this->assertEquals(5, count($usage));

        $this->assertEquals((string) $usage[0]['title'], $posts[0]->title);
        $this->assertEquals((string) $usage[1]['title'], $posts[1]->title);
        $this->assertEquals((string) $usage[2]['title'], $posts[2]->title);
        $this->assertEquals((string) $usage[3]['title'], $posts[3]->title);
        $this->assertEquals((string) $usage[4]['title'], $posts[4]->title);
    }

    #[Test]
    public function it_fires_an_augmented_hook()
    {
        $postBlueprint = Blueprint::find('runway::post');
        Blueprint::shouldReceive('find')->with('runway::BlogPosts')->andReturn($postBlueprint);

        Config::set('runway.resources.'.Post::class.'.handle', 'BlogPosts');

        Runway::discoverResources();

        $post = Post::factory()->create();

        $augmentedCount = 0;

        $post::hook('augmented', function ($payload, $next) use (&$augmentedCount) {
            $augmentedCount++;

            return $next($payload);
        });

        $this->tag->setParameters([]);
        $this->tag->wildcard('blog_posts');

        $this->assertEquals(1, $augmentedCount);
    }

    #[Test]
    public function it_can_count_models()
    {
        Post::factory()->count(3)->create();
        Post::factory()->count(2)->create(['title' => 'Foo Bar']);

        $count = $this->tag
            ->setParameters([
                'from' => 'post',
                'where' => 'title:Foo Bar',
            ])
            ->count();

        $this->assertEquals(2, $count);
    }
}
