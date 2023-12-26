<?php

namespace StatamicRadPack\Runway\Tests\Search;

use StatamicRadPack\Runway\Data\AugmentedModel;
use StatamicRadPack\Runway\Runway;
use StatamicRadPack\Runway\Search\Searchable;
use StatamicRadPack\Runway\Tests\Fixtures\Models\Post;
use StatamicRadPack\Runway\Tests\TestCase;

class SearchableTest extends TestCase
{
    /** @test */
    public function can_get_resource()
    {
        $post = Post::factory()->create();

        $searchable = new Searchable($post);

        $this->assertEquals(Runway::findResource('post'), $searchable->resource());
    }

    /** @test */
    public function can_get_queryable_value()
    {
        $post = Post::factory()->create();

        $searchable = new Searchable($post);

        $this->assertEquals($post->title, $searchable->getQueryableValue('title'));
        $this->assertEquals($post->slug, $searchable->getQueryableValue('slug'));
        $this->assertEquals($post->id, $searchable->getQueryableValue('id'));
        $this->assertEquals('default', $searchable->getQueryableValue('site'));
    }

    /** @test */
    public function can_get_search_value()
    {
        $post = Post::factory()->create();

        $searchable = new Searchable($post);

        $this->assertEquals($post->title, $searchable->getSearchValue('title'));
        $this->assertEquals($post->slug, $searchable->getSearchValue('slug'));
        $this->assertEquals($post->id, $searchable->getSearchValue('id'));
    }

    /** @test */
    public function can_get_search_reference()
    {
        $post = Post::factory()->create();

        $searchable = new Searchable($post);

        $this->assertEquals("runway::post::{$post->id}", $searchable->getSearchReference());
    }

    /** @test */
    public function can_get_search_result()
    {
        $post = Post::factory()->create();

        $searchable = new Searchable($post);

        $result = $searchable->toSearchResult();

        $this->assertEquals($searchable, $result->getSearchable());
        $this->assertEquals("runway::post::{$post->id}", $result->getReference());
        $this->assertEquals('runway:post', $result->getType());
    }

    /** @test */
    public function can_get_cp_search_result_title()
    {
        $post = Post::factory()->create();

        $searchable = new Searchable($post);

        $this->assertEquals($post->title, $searchable->getCpSearchResultTitle());
    }

    /** @test */
    public function can_get_cp_search_result_url()
    {
        $post = Post::factory()->create();

        $searchable = new Searchable($post);

        $this->assertStringContainsString("/runway/post/{$post->id}", $searchable->getCpSearchResultUrl());
    }

    /** @test */
    public function can_get_cp_search_result_badge()
    {
        $post = Post::factory()->create();

        $searchable = new Searchable($post);

        $this->assertEquals('Posts', $searchable->getCpSearchResultBadge());
    }

    /** @test */
    public function can_get_new_augmented_instance()
    {
        $post = Post::factory()->create();

        $searchable = new Searchable($post);
        $searchable->setSupplement('foo', 'bar');

        $this->assertInstanceOf(AugmentedModel::class, $searchable->newAugmentedInstance());
    }
}
