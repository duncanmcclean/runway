<?php

namespace StatamicRadPack\Runway\Tests;

use Illuminate\Support\Facades\Config;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Fieldset;
use StatamicRadPack\Runway\Runway;

class ResourceTest extends TestCase
{
    /** @test */
    public function can_get_eloquent_relationships_for_belongs_to_field()
    {
        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $eloquentRelationships = $resource->eloquentRelationships();

        $this->assertContains('author', $eloquentRelationships->toArray());
    }

    /** @test */
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

    /** @test */
    public function can_get_eloquent_relationships_for_runway_uri_routing()
    {
        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $eloquentRelationships = $resource->eloquentRelationships();

        $this->assertContains('runwayUri', $eloquentRelationships->toArray());
    }

    /** @test */
    public function can_get_eloquent_relationships_as_defined_in_config()
    {
        Config::set('runway.resources.StatamicRadPack\Runway\Tests\Fixtures\Models\Post.relationships', ['author']);

        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $eloquentRelationships = $resource->eloquentRelationships();

        $this->assertContains('author', $eloquentRelationships->toArray());
        $this->assertNotContains('runwayUri', $eloquentRelationships->toArray());
    }

    /** @test */
    public function can_get_generated_singular()
    {
        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $singular = $resource->singular();

        $this->assertEquals($singular, 'Post');
    }

    /** @test */
    public function can_get_configured_singular()
    {
        Config::set('runway.resources.StatamicRadPack\Runway\Tests\Fixtures\Models\Post.singular', 'Bibliothek');

        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $singular = $resource->singular();

        $this->assertEquals($singular, 'Bibliothek');
    }

    /** @test */
    public function can_get_generated_plural()
    {
        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $plural = $resource->plural();

        $this->assertEquals($plural, 'Posts');
    }

    /** @test */
    public function can_get_configured_plural()
    {
        Config::set('runway.resources.StatamicRadPack\Runway\Tests\Fixtures\Models\Post.plural', 'Bibliotheken');

        Runway::discoverResources();

        $resource = Runway::findResource('post');

        $plural = $resource->plural();

        $this->assertEquals($plural, 'Bibliotheken');
    }

    /** @test */
    public function can_get_blueprint()
    {
        $resource = Runway::findResource('post');

        $blueprint = $resource->blueprint();

        $this->assertTrue($blueprint instanceof \Statamic\Fields\Blueprint);
        $this->assertSame('runway', $blueprint->namespace());
        $this->assertSame('post', $blueprint->handle());
    }

    /** @test */
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

    /** @test */
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
            'seo_title',
            'seo_description',
        ], $resource->listableColumns()->toArray());
    }

    /** @test */
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

        $this->assertEquals('values->listable_shown_field', $resource->titleField());
    }
}
