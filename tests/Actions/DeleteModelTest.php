<?php

namespace StatamicRadPack\Runway\Tests\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use StatamicRadPack\Runway\Actions\DeleteModel;
use StatamicRadPack\Runway\Runway;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Post;

class DeleteModelTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    #[Test]
    public function it_returns_title()
    {
        $this->assertEquals('Delete', DeleteModel::title());
    }

    #[Test]
    public function is_visible_to_eloquent_model()
    {
        $visibleTo = (new DeleteModel)->visibleTo(Post::factory()->create());

        $this->assertTrue($visibleTo);
    }

    #[Test]
    public function is_not_visible_to_eloquent_model_when_resource_is_read_only()
    {
        Config::set('runway.resources.Workbench\App\Models\Post.read_only', true);
        Runway::discoverResources();

        $visibleTo = (new DeleteModel)->visibleTo(Post::factory()->create());

        $this->assertFalse($visibleTo);
    }

    #[Test]
    public function is_not_visible_to_eloquent_model_without_a_runway_resource()
    {
        $model = new class extends Model
        {
            protected $table = 'posts';
        };

        $visibleTo = (new DeleteModel)->visibleTo(new $model);

        $this->assertFalse($visibleTo);
    }

    #[Test]
    public function is_not_visible_to_entry()
    {
        Collection::make('posts')->save();

        $visibleTo = (new DeleteModel)->visibleTo(
            tap(Entry::make()->collection('posts')->slug('hello-world'))->save()
        );

        $this->assertFalse($visibleTo);
    }

    #[Test]
    public function is_visible_to_eloquent_models_in_bulk()
    {
        $posts = Post::factory()->count(3)->create();

        $visibleToBulk = (new DeleteModel)->visibleToBulk($posts);

        $this->assertTrue($visibleToBulk);
    }

    #[Test]
    public function is_not_visible_to_entries_in_bulk()
    {
        Collection::make('posts')->save();

        $entries = collect([
            tap(Entry::make()->collection('posts')->slug('hello-world'))->save(),
            tap(Entry::make()->collection('posts')->slug('foo-bar'))->save(),
            tap(Entry::make()->collection('posts')->slug('bye-bye'))->save(),
        ]);

        $visibleToBulk = (new DeleteModel)->visibleToBulk($entries);

        $this->assertFalse($visibleToBulk);
    }

    #[Test]
    public function super_user_is_authorized()
    {
        $user = User::make()->makeSuper()->save();

        $authorize = (new DeleteModel)->authorize($user, Post::factory()->create());

        $this->assertTrue($authorize);
    }

    #[Test]
    public function user_with_permission_is_authorized()
    {
        Role::make('editor')->addPermission('delete post')->save();

        $user = User::make()->assignRole('editor')->save();

        $authorize = (new DeleteModel)->authorize($user, Post::factory()->create());

        $this->assertTrue($authorize);

        Role::find('editor')->delete();
    }

    #[Test]
    public function user_without_permission_is_not_authorized()
    {
        $user = User::make()->save();

        $authorize = (new DeleteModel)->authorize($user, Post::factory()->create());

        $this->assertFalse($authorize);
    }

    #[Test]
    public function it_deletes_models()
    {
        $posts = Post::factory()->count(5)->create();

        $this->assertCount(5, Post::all());

        (new DeleteModel)->run($posts, []);

        $this->assertCount(0, Post::all());
    }
}
