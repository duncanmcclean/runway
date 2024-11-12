<?php

namespace StatamicRadPack\Runway\Tests\Http\Controllers\CP;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Folder;
use Statamic\Facades\User;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Post;

class RestoreModelRevisionController extends TestCase
{
    private $dir;

    public function setUp(): void
    {
        parent::setUp();

        $this->dir = __DIR__.'/tmp';
        config(['statamic.revisions.enabled' => true]);
        config(['statamic.revisions.path' => $this->dir]);
    }

    public function tearDown(): void
    {
        Folder::delete($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function it_restores_revision()
    {
        $model = Post::factory()->create(['title' => 'Some new title']);

        $revision = $model->makeRevision()
            ->message('The revision')
            ->attributes([
                'id' => $model->id,
                'published' => true,
                'data' => collect($model->getAttributes())->merge([
                    'title' => 'Original title',
                ])->all(),
            ]);

        $revision->save();

        $this
            ->actingAs(User::make()->id('user-1')->makeSuper()->save())
            ->post($model->runwayRestoreRevisionUrl(), ['revision' => $revision->id()])
            ->assertOk()
            ->assertSessionHas('success');

        $workingCopy = $model->workingCopy();

        $this->assertEquals($model->id, $workingCopy->attributes()['id']);
        $this->assertEquals('Original title', $workingCopy->attributes()['data']['title']);
    }
}
