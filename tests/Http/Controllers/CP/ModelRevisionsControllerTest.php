<?php

namespace StatamicRadPack\Runway\Tests\Http\Controllers\CP;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Folder;
use Statamic\Facades\User;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Post;

class ModelRevisionsControllerTest extends TestCase
{
    private $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = __DIR__.'/tmp';
        config(['statamic.revisions.enabled' => true]);
        config(['statamic.revisions.path' => $this->dir]);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function it_gets_revisions()
    {
        $this->travelTo('2024-01-01 10:30:00');

        $model = Post::factory()->create(['title' => 'Original title']);

        tap($model->makeRevision(), function ($copy) {
            $copy->message('Revision one');
            $copy->date(Carbon::parse('2023-12-12 15:00:00'));
        })->save();

        tap($model->makeRevision(), function ($copy) {
            $copy->message('Revision two');
            $copy->date(Carbon::parse('2024-01-01 08:00:00'));
        })->save();

        tap($model->makeWorkingCopy(), function ($copy) {
            $attrs = $copy->attributes();
            $attrs['data']['title'] = 'Title modified in working copy';
            $copy->attributes($attrs);
        })->save();

        $this
            ->actingAs(User::make()->id('user-1')->makeSuper()->save())
            ->get($model->runwayRevisionsUrl())
            ->assertOk()
            ->assertJsonPath('0.revisions.0.action', 'revision')
            ->assertJsonPath('0.revisions.0.message', 'Revision one')
            ->assertJsonPath('0.revisions.0.attributes.data.title', 'Original title')
            ->assertJsonPath('0.revisions.0.attributes.item_url', "http://localhost/cp/runway/post/{$model->id}/revisions/".Carbon::parse('2023-12-12 15:00:00')->timestamp)

            ->assertJsonPath('1.revisions.0.action', 'revision')
            ->assertJsonPath('1.revisions.0.message', false)
            ->assertJsonPath('1.revisions.0.attributes.data.title', 'Title modified in working copy')
            ->assertJsonPath('1.revisions.0.attributes.item_url', "http://localhost/cp/runway/post/{$model->id}/revisions/".Carbon::parse('2024-01-01 10:30:00')->timestamp)

            ->assertJsonPath('1.revisions.1.action', 'revision')
            ->assertJsonPath('1.revisions.1.message', 'Revision two')
            ->assertJsonPath('1.revisions.1.attributes.data.title', 'Original title')
            ->assertJsonPath('1.revisions.1.attributes.item_url', "http://localhost/cp/runway/post/{$model->id}/revisions/".Carbon::parse('2024-01-01 08:00:00')->timestamp);
    }

    #[Test]
    public function it_creates_a_revision()
    {
        $model = Post::factory()->unpublished()->create(['title' => 'Original title']);

        tap($model->makeWorkingCopy(), function ($copy) {
            $attrs = $copy->attributes();
            $attrs['data']['title'] = 'Title modified in working copy';
            $copy->attributes($attrs);
        })->save();

        $this->assertFalse($model->published());
        $this->assertCount(0, $model->revisions());

        $this
            ->actingAs(User::make()->id('user-1')->makeSuper()->save())
            ->post($model->runwayCreateRevisionUrl(), ['message' => 'Test!'])
            ->assertOk();

        $model->refresh();

        $this->assertEquals($model->title, 'Original title');
        $this->assertFalse($model->published());
        $this->assertCount(1, $model->revisions());

        $revision = $model->latestRevision();

        $this->assertEquals($model->id, $revision->attributes()['id']);
        $this->assertFalse($revision->attributes()['published']);
        $this->assertEquals('Title modified in working copy', $revision->attributes()['data']['title']);

        $this->assertEquals('user-1', $revision->user()->id());
        $this->assertEquals('Test!', $revision->message());
        $this->assertEquals('revision', $revision->action());

        $this->assertTrue($model->hasWorkingCopy());
    }

    #[Test]
    public function it_gets_revision()
    {
        $model = Post::factory()->create(['title' => 'Original title']);

        $revision = $model->makeRevision()
            ->message('The revision')
            ->date(Carbon::parse('2023-12-12 15:00:00'));

        $revision->save();

        $this
            ->actingAs(User::make()->id('user-1')->makeSuper()->save())
            ->get($model->runwayRevisionUrl($revision))
            ->assertOk()
            ->assertJson([
                'title' => 'Original title',
                'editing' => true,
                'values' => [
                    'id' => $model->id,
                    'title' => 'Original title',
                ],
                'readOnly' => true,
            ]);
    }
}
