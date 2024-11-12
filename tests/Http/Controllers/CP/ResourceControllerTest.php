<?php

namespace StatamicRadPack\Runway\Tests\Http\Controllers\CP;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Config;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Facades\UserGroup;
use StatamicRadPack\Runway\Runway;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Author;
use Workbench\App\Models\Post;
use Workbench\App\Models\User as UserModel;

class ResourceControllerTest extends TestCase
{
    #[Test]
    public function get_model_index()
    {
        Post::factory()->count(2)->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.index', ['resource' => 'post']))
            ->assertOk()
            ->assertViewIs('runway::index')
            ->assertSee([
                'filters',
                'columns',
            ]);
    }

    #[Test]
    public function can_create_resource()
    {
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.create', ['resource' => 'post']))
            ->assertOk();
    }

    #[Test]
    public function cant_create_resource_if_resource_is_read_only()
    {
        Config::set('runway.resources.'.Post::class.'.read_only', true);

        Runway::discoverResources();

        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.create', ['resource' => 'post']))
            ->assertRedirect();
    }

    #[Test]
    public function cant_create_resource_when_blueprint_is_hidden()
    {
        $blueprint = Blueprint::find('runway::post');

        Blueprint::shouldReceive('find')->with('user')->andReturn(new \Statamic\Fields\Blueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn(new \Statamic\Fields\Blueprint);
        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($blueprint->setHidden(true));

        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.create', ['resource' => 'post']))
            ->assertRedirect();
    }

    #[Test]
    public function can_store_resource()
    {
        $author = Author::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->post(cp_route('runway.store', ['resource' => 'post']), [
                'published' => true,
                'title' => 'Jingle Bells',
                'slug' => 'jingle-bells',
                'body' => 'Jingle Bells, Jingle Bells, jingle all the way...',
                'author_id' => [$author->id],
            ])
            ->assertOk()
            ->assertJsonStructure(['data', 'saved']);

        $this->assertDatabaseHas('posts', [
            'title' => 'Jingle Bells',
        ]);
    }

    #[Test]
    public function cant_store_resource_if_resource_is_read_only()
    {
        Config::set('runway.resources.'.Post::class.'.read_only', true);

        Runway::discoverResources();

        $author = Author::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->post(cp_route('runway.store', ['resource' => 'post']), [
                'title' => 'Jingle Bells',
                'slug' => 'jingle-bells',
                'body' => 'Jingle Bells, Jingle Bells, jingle all the way...',
                'author_id' => [$author->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('posts', [
            'title' => 'Jingle Bells',
        ]);
    }

    #[Test]
    public function cant_store_resource_when_blueprint_is_hidden()
    {
        $postBlueprint = Blueprint::find('runway::post');

        Blueprint::shouldReceive('find')->with('user')->andReturn(new \Statamic\Fields\Blueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn(new \Statamic\Fields\Blueprint);
        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($postBlueprint->setHidden(true));

        $author = Author::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->post(cp_route('runway.store', ['resource' => 'post']), [
                'title' => 'Jingle Bells',
                'slug' => 'jingle-bells',
                'body' => 'Jingle Bells, Jingle Bells, jingle all the way...',
                'author_id' => [$author->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('posts', [
            'title' => 'Jingle Bells',
        ]);
    }

    #[Test]
    public function can_store_resource_and_ensure_computed_field_isnt_saved_to_database()
    {
        $author = Author::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->post(cp_route('runway.store', ['resource' => 'post']), [
                'published' => true,
                'title' => 'Jingle Bells',
                'slug' => 'jingle-bells',
                'body' => 'Jingle Bells, Jingle Bells, jingle all the way...',
                'author_id' => [$author->id],
                'age' => 25, // This is the computed field
            ])
            ->assertOk()
            ->assertJsonStructure(['data', 'saved']);

        $this->assertDatabaseHas('posts', [
            'title' => 'Jingle Bells',
        ]);
    }

    #[Test]
    public function can_store_resource_and_ensure_field_isnt_saved_to_database()
    {
        $author = Author::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->post(cp_route('runway.store', ['resource' => 'post']), [
                'published' => true,
                'title' => 'Jingle Bells',
                'slug' => 'jingle-bells',
                'body' => 'Jingle Bells, Jingle Bells, jingle all the way...',
                'author_id' => [$author->id],
                'dont_save' => 25,
            ])
            ->assertOk()
            ->assertJsonStructure(['data', 'saved']);

        $this->assertDatabaseHas('posts', [
            'title' => 'Jingle Bells',
        ]);
    }

    #[Test]
    public function can_store_resource_and_ensure_appended_attribute_doesnt_attempt_to_get_saved()
    {
        $author = Author::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->post(cp_route('runway.store', ['resource' => 'post']), [
                'published' => true,
                'title' => 'Jingle Bells',
                'slug' => 'jingle-bells',
                'body' => 'Jingle Bells, Jingle Bells, jingle all the way...',
                'excerpt' => 'This is an excerpt.',
                'author_id' => [$author->id],
            ])
            ->assertOk()
            ->assertJsonStructure(['data', 'saved']);

        $this->assertDatabaseHas('posts', [
            'title' => 'Jingle Bells',
        ]);
    }

    #[Test]
    public function can_store_resource_with_nested_field()
    {
        $author = Author::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->post(cp_route('runway.store', ['resource' => 'post']), [
                'published' => true,
                'title' => 'Jingle Bells',
                'slug' => 'jingle-bells',
                'body' => 'Jingle Bells, Jingle Bells, jingle all the way...',
                'values->alt_title' => 'Batman Smells',
                'author_id' => [$author->id],
            ])
            ->assertOk()
            ->assertJsonStructure(['data', 'saved']);

        $this->assertDatabaseHas('posts', [
            'values->alt_title' => 'Batman Smells',
        ]);
    }

    #[Test]
    public function can_store_resource_and_ensure_date_comparison_validation_works()
    {
        $author = Author::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->post(cp_route('runway.store', ['resource' => 'post']), [
                'title' => 'Jingle Bells',
                'slug' => 'jingle-bells',
                'body' => 'Jingle Bells, Jingle Bells, jingle all the way...',
                'author_id' => [$author->id],
                'start_date' => [
                    'date' => '2023-09-01',
                    'time' => '00:00',
                ],
                'end_date' => [
                    'date' => '2023-08-01',
                    'time' => '00:00',
                ],
            ])
            ->assertSessionHasErrors('start_date');

        $this->assertDatabaseMissing('posts', [
            'title' => 'Jingle Bells',
        ]);
    }

    #[Test]
    public function can_edit_resource()
    {
        $post = Post::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.edit', ['resource' => 'post', 'model' => $post->id]))
            ->assertOk()
            ->assertSee($post->title)
            ->assertSee($post->body);
    }

    #[Test]
    public function cant_edit_resource_when_it_does_not_exist()
    {
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.edit', ['resource' => 'post', 'model' => 12345]))
            ->assertNotFound()
            ->assertSee('Page Not Found');
    }

    #[Test]
    public function can_edit_resource_with_simple_date_field()
    {
        $postBlueprint = Blueprint::find('runway::post');

        Blueprint::shouldReceive('find')->with('user')->andReturn(new \Statamic\Fields\Blueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn(new \Statamic\Fields\Blueprint);
        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($postBlueprint->ensureField('created_at', [
            'type' => 'date',
            'mode' => 'single',
            'time_enabled' => false,
            'time_required' => false,
        ]));

        $user = User::make()->makeSuper()->save();
        $post = Post::factory()->create();

        $resource = Runway::findResource('post');
        $model = $resource->model()->where($resource->routeKey(), $post->getKey())->first();

        $this->assertEquals($post->getKey(), $model->getKey());

        $response = $this
            ->actingAs($user)
            ->get(cp_route('runway.edit', [
                'resource' => 'post',
                'model' => $post->id,
            ]))
            ->assertOk();

        $this->assertEquals(
            [
                'date' => $post->created_at->format('Y-m-d'),
                'time' => null,
            ],
            $response->viewData('values')['created_at']
        );
    }

    #[Test]
    public function can_edit_resource_with_date_field_with_default_format()
    {
        $postBlueprint = Blueprint::find('runway::post');

        Blueprint::shouldReceive('find')->with('user')->andReturn(new \Statamic\Fields\Blueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn(new \Statamic\Fields\Blueprint);
        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($postBlueprint->ensureField('created_at', [
            'type' => 'date',
            'mode' => 'single',
            'format' => 'Y-m-d',
            'time_enabled' => false,
            'time_required' => false,
        ]));

        $post = Post::factory()->create();
        $user = User::make()->makeSuper()->save();

        $resource = Runway::findResource('post');
        $model = $resource->model()->where($resource->routeKey(), $post->getKey())->first();

        $this->assertEquals($post->getKey(), $model->getKey());

        $response = $this
            ->actingAs($user)
            ->get(cp_route('runway.edit', [
                'resource' => 'post',
                'model' => $post->id,
            ]))
            ->assertOk();

        $this->assertEquals(
            [
                'date' => $post->created_at->format('Y-m-d'),
                'time' => null,
            ],
            $response->viewData('values')['created_at']
        );
    }

    #[Test]
    public function can_edit_resource_with_date_field_with_custom_format()
    {
        $postBlueprint = Blueprint::find('runway::post');

        Blueprint::shouldReceive('find')->with('user')->andReturn(new \Statamic\Fields\Blueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn(new \Statamic\Fields\Blueprint);
        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($postBlueprint->ensureField('created_at', [
            'type' => 'date',
            'mode' => 'single',
            'format' => 'Y-m-d H:i',
            'time_enabled' => true,
            'time_required' => false,
        ]));

        $post = Post::factory()->create();
        $user = User::make()->makeSuper()->save();

        $resource = Runway::findResource('post');
        $model = $resource->model()->where($resource->routeKey(), $post->getKey())->first();

        $this->assertEquals($post->getKey(), $model->getKey());

        $response = $this
            ->actingAs($user)
            ->get(cp_route('runway.edit', [
                'resource' => 'post',
                'model' => $post->id,
            ]))
            ->assertOk();

        $this->assertEquals(
            [
                'date' => $post->created_at->format('Y-m-d'),
                'time' => $post->created_at->format('H:i'),
            ],
            $response->viewData('values')['created_at']
        );
    }

    #[Test]
    public function can_edit_resource_with_nested_field()
    {
        $post = Post::factory()->create([
            'values' => [
                'alt_title' => 'Im Toby Ziegler, and I work at the White House.',
            ],
        ]);

        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.edit', ['resource' => 'post', 'model' => $post->id]))
            ->assertOk()
            ->assertSee($post->title)
            ->assertSee($post->body)
            ->assertSee('Im Toby Ziegler, and I work at the White House.');
    }

    #[Test]
    public function can_edit_resource_if_resource_is_read_only()
    {
        Config::set('runway.resources.'.Post::class.'.read_only', true);

        Runway::discoverResources();

        $post = Post::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->get(cp_route('runway.edit', ['resource' => 'post', 'model' => $post->id]))
            ->assertOk()
            ->assertSee($post->title)
            ->assertSee($post->body);
    }

    #[Test]
    public function can_edit_resource_with_nested_field_cast_to_object_in_model()
    {
        $post = Post::factory()->create([
            'external_links' => [
                'links' => [
                    ['label' => 'NORAD Santa Tracker', 'url' => 'noradsanta.org'],
                    ['label' => 'North Pole HQ', 'url' => 'northpole.com'],
                ],
            ],
        ]);

        $user = User::make()->makeSuper()->save();

        $this->actingAs($user)
            ->get(cp_route('runway.edit', ['resource' => 'post', 'model' => $post->id]))
            ->assertOk()
            ->assertSee($post->external_links->links[0]->label)
            ->assertSee($post->external_links->links[1]->url);
    }

    #[Test]
    public function can_edit_resource_if_model_is_user_model()
    {
        Config::set('auth.providers.users.model', UserModel::class);

        $user = UserModel::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
        ]);

        UserGroup::make('admins')->title('Admins')->save();
        DB::table('group_user')->insert(['user_id' => $user->id, 'group_id' => 'admins']);

        Role::make('developer')->title('Developer')->save();
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => 'developer']);

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->get(cp_route('runway.edit', ['resource' => 'user', 'model' => $user->id]))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->email)
            ->assertSee('developer')
            ->assertSee('admins');
    }

    #[Test]
    public function can_update_resource()
    {
        $post = Post::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->patch(cp_route('runway.update', ['resource' => 'post', 'model' => $post->id]), [
                'published' => true,
                'title' => 'Santa is coming home',
                'slug' => 'santa-is-coming-home',
                'body' => $post->body,
                'author_id' => [$post->author_id],
            ])
            ->assertOk()
            ->assertJsonStructure(['data', 'saved']);

        $post->refresh();

        $this->assertEquals($post->title, 'Santa is coming home');
    }

    #[Test]
    public function cant_update_resource_if_resource_is_read_only()
    {
        Config::set('runway.resources.'.Post::class.'.read_only', true);

        Runway::discoverResources();

        $post = Post::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->patch(cp_route('runway.update', ['resource' => 'post', 'model' => $post->id]), [
                'title' => 'Santa is coming home',
                'slug' => 'santa-is-coming-home',
                'body' => $post->body,
                'author_id' => [$post->author_id],
            ])
            ->assertRedirect();

        $post->refresh();

        $this->assertNotSame($post->title, 'Santa is coming home');
    }

    #[Test]
    public function can_update_resource_and_ensure_computed_field_isnt_saved_to_database()
    {
        $post = Post::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->patch(cp_route('runway.update', ['resource' => 'post', 'model' => $post->id]), [
                'published' => true,
                'title' => 'Santa is coming home',
                'slug' => 'santa-is-coming-home',
                'body' => $post->body,
                'author_id' => [$post->author_id],
                'age' => 19, // This is the computed field
            ])
            ->assertOk()
            ->assertJsonStructure(['data', 'saved']);

        $post->refresh();

        $this->assertEquals($post->title, 'Santa is coming home');
    }

    #[Test]
    public function can_update_resource_and_ensure__field_isnt_saved_to_database()
    {
        $post = Post::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->patch(cp_route('runway.update', ['resource' => 'post', 'model' => $post->id]), [
                'published' => true,
                'title' => 'Santa is coming home',
                'slug' => 'santa-is-coming-home',
                'body' => $post->body,
                'author_id' => [$post->author_id],
                'dont_save' => 19,
            ])
            ->assertOk()
            ->assertJsonStructure(['data', 'saved']);

        $post->refresh();

        $this->assertEquals($post->title, 'Santa is coming home');
    }

    #[Test]
    public function can_update_resource_and_ensure_appended_attribute_doesnt_attempt_to_get_saved()
    {
        $post = Post::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->patch(cp_route('runway.update', ['resource' => 'post', 'model' => $post->id]), [
                'published' => true,
                'title' => 'Santa is coming home',
                'slug' => 'santa-is-coming-home',
                'body' => $post->body,
                'excerpt' => 'This is an excerpt.',
                'author_id' => [$post->author_id],
            ])
            ->assertOk()
            ->assertJsonStructure(['data', 'saved']);

        $post->refresh();

        $this->assertEquals($post->title, 'Santa is coming home');
    }

    #[Test]
    public function can_update_resource_with_nested_field()
    {
        $post = Post::factory()->create();
        $user = User::make()->makeSuper()->save();

        $this
            ->actingAs($user)
            ->patch(cp_route('runway.update', ['resource' => 'post', 'model' => $post->id]), [
                'published' => true,
                'title' => 'Santa is coming home',
                'slug' => 'santa-is-coming-home',
                'body' => $post->body,
                'values->alt_title' => 'Claus is venturing out',
                'author_id' => [$post->author_id],
            ])
            ->assertOk()
            ->assertJsonStructure(['data', 'saved']);

        $post->refresh();

        $this->assertEquals($post->values['alt_title'], 'Claus is venturing out');
    }

    #[Test]
    public function can_update_resource_if_model_is_user_model()
    {
        Config::set('auth.providers.users.model', UserModel::class);

        $user = UserModel::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
        ]);

        UserGroup::make('admins')->title('Admins')->save();
        Role::make('developer')->title('Developer')->save();

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->patch(cp_route('runway.update', ['resource' => 'user', 'model' => $user->id]), [
                'name' => 'Jane Doe',
                'email' => 'jane.doe@example.com',
                'roles' => ['developer'],
                'groups' => ['admins'],
            ])
            ->assertOk()
            ->assertJsonStructure(['data', 'saved']);

        $user->refresh();

        $this->assertEquals($user->name, 'Jane Doe');

        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => 'developer',
        ]);

        $this->assertDatabaseHas('group_user', [
            'user_id' => $user->id,
            'group_id' => 'admins',
        ]);
    }
}
