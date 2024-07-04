<?php

namespace StatamicRadPack\Runway\Tests\Http\Controllers\CP;

use Statamic\Facades\Folder;
use Statamic\Facades\User;
use StatamicRadPack\Runway\Tests\Fixtures\Models\Post;
use StatamicRadPack\Runway\Tests\TestCase;

class PublishedModelsControllerTest extends TestCase
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

    /** @test */
    public function can_publish_a_model()
    {
        $model = Post::factory()->unpublished()->create();

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post($model->runwayPublishUrl(), [
                'message' => 'Live live live!',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'reference',
                    'title',
                    'published',
                ],
            ]);

        $this->assertTrue($model->fresh()->published());
    }

    /** @test */
    public function can_unpublish_a_model()
    {
        $model = Post::factory()->create();

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post($model->runwayUnpublishUrl(), [
                'message' => 'Live live live!',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'reference',
                    'title',
                    'published',
                ],
            ]);

        $this->assertFalse($model->fresh()->published());
    }
}
