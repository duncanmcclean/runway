<?php

namespace StatamicRadPack\Runway\Tests\Http\Controllers\CP;

use Statamic\Actions\Action;
use Statamic\Facades\User;
use StatamicRadPack\Runway\Tests\Fixtures\Models\Post;
use StatamicRadPack\Runway\Tests\TestCase;

class ResourceActionControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        FooAction::register();
    }

    /** @test */
    public function can_run_action()
    {
        $post = Post::factory()->create();

        $this->assertFalse(FooAction::$hasRun);

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/runway/post/actions', [
                'action' => 'foo',
                'selections' => [$post->id],
                'values' => [],
            ])
            ->assertOk()
            ->assertJson(['message' => 'Foo action run!']);

        $this->assertTrue(FooAction::$hasRun);
    }

    /** @test */
    public function can_get_bulk_actions_list()
    {
        $post = Post::factory()->create();

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/runway/post/actions/list', [
                'selections' => [$post->id],
            ])
            ->assertOk()
            ->assertJsonPath('0.handle', 'unpublish');
    }
}

class FooAction extends Action
{
    protected static $handle = 'foo';

    public static bool $hasRun = false;

    public function run($items, $values)
    {
        static::$hasRun = true;

        return 'Foo action run!';
    }
}
