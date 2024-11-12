<?php

namespace StatamicRadPack\Runway\Tests;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Fieldset;
use StatamicRadPack\Runway\Runway;

class ResourceTest extends TestCase
{
    #[Test]
    public function can_get_eloquent_relationships_for_belongs_to_field()
    {
        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $eloquentRelationships = $resource->eloquentRelationships();

        $this->assertContains('author', $eloquentRelationships->toArray());
    }

    #[Test]
    public function can_get_eloquent_relationships_for_has_many_field()
    {
        $blueprint = Blueprint::find('runway::author');

        Blueprint::shouldReceive('find')
            ->with('runway::author')
            ->andReturn($blueprint->ensureField('posts', [
                'type' => 'has_many',
                'resource' => 'post',
                'max_items' => 1,
                'mode' => 'default',
            ]));

        $resource = Runway::findResource('author');

        $eloquentRelationships = $resource->eloquentRelationships();

        $this->assertContains('posts', $eloquentRelationships->toArray());
    }

    #[Test]
    public function can_get_eloquent_relationships_for_runway_uri_routing()
    {
        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $eloquentRelationships = $resource->eloquentRelationships();

        $this->assertContains('runwayUri', $eloquentRelationships->toArray());
    }

    #[Test]
    public function can_get_eager_loading_relationships()
    {
        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $eagerLoadingRelationships = $resource->eagerLoadingRelationships();

        $this->assertEquals([
            'author',
            'runwayUri',
        ], $eagerLoadingRelationships);
    }

    #[Test]
    public function can_get_eager_loading_relationships_from_config()
    {
        Config::set('runway.resources.Workbench\App\Models\Post.with', [
            'author',
        ]);

        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $eagerLoadingRelationships = $resource->eagerLoadingRelationships();

        $this->assertEquals([
            'author',
        ], $eagerLoadingRelationships);
    }

    #[Test]
    public function can_get_generated_singular()
    {
        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $singular = $resource->singular();

        $this->assertEquals($singular, 'Post');
    }

    #[Test]
    public function can_get_configured_singular()
    {
        Config::set('runway.resources.Workbench\App\Models\Post.singular', 'Bibliothek');

        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $singular = $resource->singular();

        $this->assertEquals($singular, 'Bibliothek');
    }

    #[Test]
    public function can_get_generated_plural()
    {
        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $plural = $resource->plural();

        $this->assertEquals($plural, 'Posts');
    }

    #[Test]
    public function can_get_configured_plural()
    {
        Config::set('runway.resources.Workbench\App\Models\Post.plural', 'Bibliotheken');

        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $plural = $resource->plural();

        $this->assertEquals($plural, 'Bibliotheken');
    }

    #[Test]
    public function can_get_blueprint()
    {
        $resource = Runway::findResource('post');

        $blueprint = $resource->blueprint();

        $this->assertTrue($blueprint instanceof \Statamic\Fields\Blueprint);
        $this->assertSame('runway', $blueprint->namespace());
        $this->assertSame('post', $blueprint->handle());
    }

    #[Test]
    public function can_create_blueprint_if_one_does_not_exist()
    {
        $resource = Runway::findResource('post');

        Blueprint::shouldReceive('find')->with('runway::post')->andReturnNull()->once();
        Blueprint::shouldReceive('find')->with('runway.post')->andReturnNull()->once();
        Blueprint::shouldReceive('make')->with('post')->andReturn((new \Statamic\Fields\Blueprint)->setHandle('post'))->once();
        Blueprint::shouldReceive('save')->andReturnSelf()->once();

        $blueprint = $resource->blueprint();

        $this->assertTrue($blueprint instanceof \Statamic\Fields\Blueprint);
        $this->assertSame('runway', $blueprint->namespace());
        $this->assertSame('post', $blueprint->handle());
    }

    #[Test]
    public function can_get_listable_columns()
    {
        Fieldset::make('seo')->setContents([
            'fields' => [
                ['handle' => 'seo_title', 'field' => ['type' => 'text', 'listable' => true]],
                ['handle' => 'seo_description', 'field' => ['type' => 'textarea', 'listable' => true]],
            ],
        ])->save();

        $blueprint = Blueprint::make()->setContents([
            'tabs' => [
                'main' => [
                    'sections' => [
                        [
                            'fields' => [
                                ['handle' => 'title', 'field' => ['type' => 'text', 'listable' => true]],
                                ['handle' => 'summary', 'field' => ['type' => 'textarea', 'listable' => true]],
                                ['handle' => 'body', 'field' => ['type' => 'markdown', 'listable' => 'hidden']],
                                ['handle' => 'thumbnail', 'field' => ['type' => 'assets', 'listable' => false]],
                                ['import' => 'seo'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($blueprint);

        $resource = Runway::findResource('post');

        $this->assertEquals([
            'title',
            'summary',
            'body',
            'seo_title',
            'seo_description',
        ], $resource->listableColumns()->toArray());
    }

    #[Test]
    public function can_get_title_field()
    {
        $blueprint = Blueprint::make()->setContents([
            'tabs' => [
                'main' => [
                    'sections' => [
                        [
                            'fields' => [
                                ['handle' => 'values->listable_hidden_field', 'field' => ['type' => 'text', 'listable' => 'hidden']],
                                ['handle' => 'values->listable_shown_field', 'field' => ['type' => 'text', 'listable' => true]],
                                ['handle' => 'values->not_listable_field', 'field' => ['type' => 'text', 'listable' => false]],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($blueprint);

        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $this->assertEquals('values->listable_hidden_field', $resource->titleField());
    }

    #[Test]
    public function revisions_can_be_enabled()
    {
        Config::set('statamic.editions.pro', true);
        Config::set('statamic.revisions.enabled', true);

        Config::set('runway.resources.Workbench\App\Models\Post.published', true);
        Config::set('runway.resources.Workbench\App\Models\Post.revisions', true);

        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $this->assertTrue($resource->revisionsEnabled());
    }

    #[Test]
    public function revisions_cant_be_enabled_without_revisions_being_enabled_globally()
    {
        Config::set('statamic.editions.pro', true);
        Config::set('statamic.revisions.enabled', false);

        Config::set('runway.resources.Workbench\App\Models\Post.published', true);
        Config::set('runway.resources.Workbench\App\Models\Post.revisions', true);

        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $this->assertFalse($resource->revisionsEnabled());
    }

    #[Test]
    public function revisions_cant_be_enabled_without_statamic_pro()
    {
        Config::set('statamic.editions.pro', false);
        Config::set('statamic.revisions.enabled', true);

        Config::set('runway.resources.Workbench\App\Models\Post.published', true);
        Config::set('runway.resources.Workbench\App\Models\Post.revisions', true);

        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $this->assertFalse($resource->revisionsEnabled());
    }

    #[Test]
    public function revisions_cant_be_enabled_without_publish_states()
    {
        Config::set('statamic.editions.pro', true);
        Config::set('statamic.revisions.enabled', true);

        Config::set('runway.resources.Workbench\App\Models\Post.published', false);
        Config::set('runway.resources.Workbench\App\Models\Post.revisions', true);

        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $this->assertFalse($resource->revisionsEnabled());
    }
}
