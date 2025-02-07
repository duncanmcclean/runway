<?php

namespace StatamicRadPack\Runway\Tests\Http\Controllers\CP;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Actions\Action;
use Statamic\Facades\User;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Post;

class ResourceActionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FooAction::register();
    }

    #[Test]
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

    #[Test]
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
