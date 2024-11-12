<?php

namespace StatamicRadPack\Runway\Tests\Http\Controllers\CP;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Blink;
use Statamic\Facades\User;
use StatamicRadPack\Runway\Runway;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Author;
use Workbench\App\Models\Post;

class ResourceListingControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    #[Test]
    public function user_with_no_permissions_cannot_access_resource_listing()
    {
        $this
            ->actingAs(User::make()->save())
            ->get(cp_route('runway.listing-api', ['resource' => 'post']))
            ->assertRedirect();
    }

    #[Test]
    public function can_sort_listing_rows()
    {
        $user = User::make()->makeSuper()->save();
        $posts = Post::factory()->count(2)->create();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.listing-api', ['resource' => 'post']))
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'title' => $posts[0]->title,
                        'edit_url' => "http://localhost/cp/runway/post/{$posts[0]->id}",
                        'id' => $posts[0]->id,
                    ],
                    [
                        'title' => $posts[1]->title,
                        'edit_url' => "http://localhost/cp/runway/post/{$posts[1]->id}",
                        'id' => $posts[1]->id,
                    ],
                ],
            ]);
    }

    #[Test]
    public function listing_rows_are_ordered_as_per_config()
    {
        Config::set('runway.resources.Workbench\App\Models\Post.order_by', 'id');
        Config::set('runway.resources.Workbench\App\Models\Post.order_by_direction', 'desc');

        Runway::discoverResources();

        $user = User::make()->makeSuper()->save();
        $posts = Post::factory()->count(2)->create();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.listing-api', ['resource' => 'post']))
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'title' => $posts[1]->title,
                        'edit_url' => "http://localhost/cp/runway/post/{$posts[1]->id}",
                        'id' => $posts[1]->id,
                    ],
                    [
                        'title' => $posts[0]->title,
                        'edit_url' => "http://localhost/cp/runway/post/{$posts[0]->id}",
                        'id' => $posts[0]->id,
                    ],
                ],
            ]);
    }

    #[Test]
    public function listing_rows_are_ordered_from_runway_listing_scope()
    {
        $user = User::make()->makeSuper()->save();
        $posts = Post::factory()->count(2)->create();

        Blink::put('RunwayListingScopeOrderBy', ['id', 'desc']);

        $this
            ->actingAs($user)
            ->get(cp_route('runway.listing-api', ['resource' => 'post']))
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'title' => $posts[1]->title,
                        'edit_url' => "http://localhost/cp/runway/post/{$posts[1]->id}",
                        'id' => $posts[1]->id,
                    ],
                    [
                        'title' => $posts[0]->title,
                        'edit_url' => "http://localhost/cp/runway/post/{$posts[0]->id}",
                        'id' => $posts[0]->id,
                    ],
                ],
            ]);
    }

    #[Test]
    public function listing_rows_arent_ordered_from_runway_listing_scope_when_user_defines_an_order()
    {
        $user = User::make()->makeSuper()->save();
        $posts = Post::factory()->count(2)->create();

        Blink::put('RunwayListingScopeOrderBy', ['id', 'desc']);

        $this
            ->actingAs($user)
            ->get(cp_route('runway.listing-api', ['resource' => 'post', 'sort' => 'id', 'order' => 'asc']))
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'title' => $posts[0]->title,
                        'edit_url' => "http://localhost/cp/runway/post/{$posts[0]->id}",
                        'id' => $posts[0]->id,
                    ],
                    [
                        'title' => $posts[1]->title,
                        'edit_url' => "http://localhost/cp/runway/post/{$posts[1]->id}",
                        'id' => $posts[1]->id,
                    ],
                ],
            ]);
    }

    #[Test]
    public function listing_rows_are_ordered_when_user_defines_an_order_and_no_runway_listing_scope_order_exists()
    {
        $user = User::make()->makeSuper()->save();
        $posts = Post::factory()->count(2)->create();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.listing-api', ['resource' => 'post', 'sort' => 'id', 'order' => 'desc']))
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'title' => $posts[1]->title,
                        'edit_url' => "http://localhost/cp/runway/post/{$posts[1]->id}",
                        'id' => $posts[1]->id,
                    ],
                    [
                        'title' => $posts[0]->title,
                        'edit_url' => "http://localhost/cp/runway/post/{$posts[0]->id}",
                        'id' => $posts[0]->id,
                    ],
                ],
            ]);
    }

    #[Test]
    public function can_search()
    {
        $user = User::make()->makeSuper()->save();
        $posts = Post::factory()->count(2)->create();

        $posts[0]->update(['title' => 'Apple Pie']);

        $this
            ->actingAs($user)
            ->get(cp_route('runway.listing-api', ['resource' => 'post', 'search' => 'Apple']))
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'title' => $posts[0]->title,
                        'edit_url' => "http://localhost/cp/runway/post/{$posts[0]->id}",
                        'id' => $posts[0]->id,
                    ],
                ],
            ]);
    }

    #[Test]
    public function can_search_models_with_has_many_relationship()
    {
        $user = User::make()->makeSuper()->save();
        $author = Author::factory()->withPosts()->create(['name' => 'Colin The Caterpillar']);

        $this
            ->actingAs($user)
            ->get(cp_route('runway.listing-api', [
                'resource' => 'author',
                'search' => 'Colin',
                'columns' => 'name,posts',
            ]))
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'name' => 'Colin The Caterpillar',
                        'edit_url' => "http://localhost/cp/runway/author/{$author->id}",
                        'id' => $author->id,
                    ],
                ],
            ]);
    }

    #[Test]
    public function can_search_using_a_search_index()
    {
        Config::set('statamic.search.indexes.test_search_index', [
            'driver' => 'local',
            'searchables' => ['runway:post'],
            'fields' => ['title', 'slug'],
        ]);

        Config::set('runway.resources.Workbench\App\Models\Post.search_index', 'test_search_index');

        Runway::discoverResources();

        $user = User::make()->makeSuper()->save();
        $posts = Post::factory()->count(2)->create();

        $posts[0]->update(['title' => 'Apple Pie']);

        $this
            ->actingAs($user)
            ->get(cp_route('runway.listing-api', ['resource' => 'post', 'search' => 'Apple']))
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'title' => $posts[0]->title,
                        'edit_url' => "http://localhost/cp/runway/post/{$posts[0]->id}",
                        'id' => $posts[0]->id,
                    ],
                ],
            ]);
    }

    #[Test]
    public function can_paginate_results()
    {
        Post::factory()->count(15)->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.listing-api', ['resource' => 'post']).'?perPage=5')
            ->assertOk()
            ->assertJson([
                'meta' => [
                    'per_page' => '5',
                    'to' => 5,
                    'total' => 15,
                ],
            ]);
    }

    #[Test]
    public function can_get_values_from_nested_fields()
    {
        $posts = Post::factory()->count(3)->create([
            'values' => [
                'alt_title' => $this->faker()->words(6, true),
            ],
        ]);

        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.listing-api', ['resource' => 'post']).'?columns=title,values->alt_title')
            ->assertOk()
            ->assertSee($posts[0]->values['alt_title'])
            ->assertSee($posts[1]->values['alt_title'])
            ->assertSee($posts[2]->values['alt_title']);
    }
}
