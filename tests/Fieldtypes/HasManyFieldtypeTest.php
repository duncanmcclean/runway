<?php

namespace StatamicRadPack\Runway\Tests\Fieldtypes;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\Fields\Field;
use Statamic\Http\Requests\FilteredRequest;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use StatamicRadPack\Runway\Fieldtypes\HasManyFieldtype;
use StatamicRadPack\Runway\Runway;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Author;
use Workbench\App\Models\Post;

class HasManyFieldtypeTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk, WithFaker;

    protected HasManyFieldtype $fieldtype;

    public function setUp(): void
    {
        parent::setUp();

        $this->fieldtype = tap(new HasManyFieldtype)
            ->setField(new Field('posts', [
                'mode' => 'stack',
                'resource' => 'post',
                'display' => 'Posts',
                'type' => 'has_many',
            ]));
    }

    #[Test]
    public function unlink_behavior_is_unlink_when_relationship_column_is_nullable()
    {
        Schema::shouldReceive('getColumns')->with('posts')->andReturn([
            ['name' => 'author_id', 'type' => 'integer', 'nullable' => true],
        ]);

        Schema::shouldIgnoreMissing();

        $author = Author::factory()->create();

        $field = new Field('posts', [
            'mode' => 'stack',
            'resource' => 'post',
            'display' => 'Posts',
            'type' => 'has_many',
        ]);

        $field->setParent($author);

        $fieldtype = new HasManyFieldtype;
        $fieldtype->setField($field);

        $this->assertEquals('unlink', $fieldtype->preload()['unlinkBehavior']);
    }

    #[Test]
    public function unlink_behavior_is_delete_when_relationship_column_is_not_nullable()
    {
        Schema::shouldReceive('getColumns')->with('posts')->andReturn([
            ['name' => 'author_id', 'type' => 'integer', 'nullable' => false],
        ]);

        Schema::shouldIgnoreMissing();

        $author = Author::factory()->create();

        $field = new Field('posts', [
            'mode' => 'stack',
            'resource' => 'post',
            'display' => 'Posts',
            'type' => 'has_many',
        ]);

        $field->setParent($author);

        $fieldtype = new HasManyFieldtype;
        $fieldtype->setField($field);

        $this->assertEquals('delete', $fieldtype->preload()['unlinkBehavior']);
    }

    #[Test]
    public function unlink_behavior_is_unlink_when_field_is_used_on_entry()
    {
        \Statamic\Facades\Collection::make('pages')->save();

        $field = new Field('posts', [
            'mode' => 'stack',
            'resource' => 'post',
            'display' => 'Posts',
            'type' => 'has_many',
        ]);

        $field->setParent(Entry::make()->collection('pages'));

        $fieldtype = new HasManyFieldtype;
        $fieldtype->setField($field);

        $this->assertEquals('unlink', $fieldtype->preload()['unlinkBehavior']);
    }

    #[Test]
    public function can_get_index_items()
    {
        $author = Author::factory()->create();
        Post::factory()->count(10)->create(['author_id' => $author->id]);

        $getIndexItemsWithPagination = $this->fieldtype->getIndexItems(
            new FilteredRequest(['paginate' => true])
        );

        $getIndexItemsWithoutPagination = $this->fieldtype->getIndexItems(
            new FilteredRequest(['paginate' => false])
        );

        $this->assertIsObject($getIndexItemsWithPagination);
        $this->assertTrue($getIndexItemsWithPagination instanceof Paginator);
        $this->assertEquals($getIndexItemsWithPagination->count(), 10);

        $this->assertIsObject($getIndexItemsWithoutPagination);
        $this->assertTrue($getIndexItemsWithoutPagination instanceof Collection);
        $this->assertEquals($getIndexItemsWithoutPagination->count(), 10);
    }

    #[Test]
    public function can_get_index_items_in_order_specified_in_runway_config()
    {
        Config::set('runway.resources.Workbench\App\Models\Post.order_by', 'title');
        Config::set('runway.resources.Workbench\App\Models\Post.order_by_direction', 'asc');

        Runway::discoverResources();

        Post::factory()->create(['title' => 'Arnold A']);
        Post::factory()->create(['title' => 'Richard B']);
        Post::factory()->create(['title' => 'Graham C']);

        $getIndexItems = $this->fieldtype->getIndexItems(new FilteredRequest(['paginate' => false]));

        $this->assertIsObject($getIndexItems);
        $this->assertTrue($getIndexItems instanceof Collection);
        $this->assertEquals($getIndexItems->count(), 3);

        $this->assertEquals($getIndexItems->all()[0]['title'], 'Arnold A');
        $this->assertEquals($getIndexItems->all()[1]['title'], 'Graham C');
        $this->assertEquals($getIndexItems->all()[2]['title'], 'Richard B');
    }

    #[Test]
    public function can_get_index_items_in_order_from_runway_listing_scope()
    {
        Post::factory()->create(['title' => 'Arnold A']);
        Post::factory()->create(['title' => 'Richard B']);
        Post::factory()->create(['title' => 'Graham C']);

        Blink::put('RunwayListingScopeOrderBy', ['title', 'asc']);

        $getIndexItems = $this->fieldtype->getIndexItems(new FilteredRequest(['paginate' => false]));

        $this->assertIsObject($getIndexItems);
        $this->assertTrue($getIndexItems instanceof Collection);
        $this->assertEquals($getIndexItems->count(), 3);

        $this->assertEquals($getIndexItems->all()[0]['title'], 'Arnold A');
        $this->assertEquals($getIndexItems->all()[1]['title'], 'Graham C');
        $this->assertEquals($getIndexItems->all()[2]['title'], 'Richard B');
    }

    #[Test]
    public function can_get_index_items_in_order_from_runway_listing_scope_when_user_defines_an_order()
    {
        Post::factory()->create(['title' => 'Arnold A']);
        Post::factory()->create(['title' => 'Richard B']);
        Post::factory()->create(['title' => 'Graham C']);

        Blink::put('RunwayListingScopeOrderBy', ['title', 'asc']);

        $getIndexItems = $this->fieldtype->getIndexItems(new FilteredRequest(['paginate' => false, 'sort' => 'title', 'order' => 'desc']));

        $this->assertIsObject($getIndexItems);
        $this->assertTrue($getIndexItems instanceof Collection);
        $this->assertEquals($getIndexItems->count(), 3);

        $this->assertEquals($getIndexItems->all()[0]['title'], 'Richard B');
        $this->assertEquals($getIndexItems->all()[1]['title'], 'Graham C');
        $this->assertEquals($getIndexItems->all()[2]['title'], 'Arnold A');
    }

    #[Test]
    public function can_get_index_items_and_search()
    {
        $author = Author::factory()->create();
        Post::factory()->count(10)->create(['author_id' => $author->id]);
        $spacePandaPosts = Post::factory()->count(3)->create(['author_id' => $author->id, 'title' => 'Space Pandas']);

        $getIndexItems = $this->fieldtype->getIndexItems(
            new FilteredRequest(['search' => 'space pan'])
        );

        $this->assertIsObject($getIndexItems);
        $this->assertTrue($getIndexItems instanceof Paginator);
        $this->assertEquals($getIndexItems->count(), 3);

        $this->assertEquals($getIndexItems->first()['title'], $spacePandaPosts[0]->title);
        $this->assertEquals($getIndexItems->last()['title'], $spacePandaPosts[1]->title);
        $this->assertEquals($getIndexItems->last()['title'], $spacePandaPosts[2]->title);
    }

    #[Test]
    public function can_get_index_items_and_search_using_a_search_index()
    {
        Config::set('statamic.search.indexes.test_search_index', [
            'driver' => 'local',
            'searchables' => ['runway:post'],
            'fields' => ['title', 'slug'],
        ]);

        Config::set('runway.resources.Workbench\App\Models\Post.search_index', 'test_search_index');

        Runway::discoverResources();

        $author = Author::factory()->create();
        Post::factory()->count(10)->create(['author_id' => $author->id]);
        $spacePandaPosts = Post::factory()->count(3)->create(['author_id' => $author->id, 'title' => 'Space Pandas']);

        $getIndexItems = $this->fieldtype->getIndexItems(
            new FilteredRequest(['search' => 'space pan'])
        );

        $this->assertIsObject($getIndexItems);
        $this->assertTrue($getIndexItems instanceof Paginator);
        $this->assertEquals($getIndexItems->count(), 3);

        $this->assertEquals($getIndexItems->first()['title'], $spacePandaPosts[0]->title);
        $this->assertEquals($getIndexItems->last()['title'], $spacePandaPosts[1]->title);
        $this->assertEquals($getIndexItems->last()['title'], $spacePandaPosts[2]->title);
    }

    #[Test]
    public function can_get_item_array_with_title_format()
    {
        $author = Author::factory()->create();
        $posts = Post::factory()->count(2)->create(['author_id' => $author->id]);

        $this->fieldtype->setField(new Field('posts', [
            'mode' => 'default',
            'resource' => 'post',
            'display' => 'Posts',
            'type' => 'has_many',
            'title_format' => '{{ title }} TEST {{ created_at format="Y" }}',
        ]));

        $item = $this->fieldtype->getItemData([$posts[0]->id, $posts[1]->id]);

        $this->assertEquals($item->first()['title'], $posts[0]->title.' TEST '.now()->format('Y'));
        $this->assertEquals($item->last()['title'], $posts[1]->title.' TEST '.now()->format('Y'));
    }

    #[Test]
    public function can_get_pre_process_index()
    {
        $author = Author::factory()->create();
        $posts = Post::factory()->count(10)->create(['author_id' => $author->id]);

        $preProcessIndex = $this->fieldtype->preProcessIndex($author->posts);

        $this->assertTrue($preProcessIndex instanceof Collection);

        $this->assertEquals($preProcessIndex->first(), [
            'id' => $posts[0]->id,
            'title' => $posts[0]->title,
            'edit_url' => 'http://localhost/cp/runway/post/'.$posts[0]->id,
        ]);
    }

    #[Test]
    public function can_get_augment_value()
    {
        $author = Author::factory()->create();
        $posts = Post::factory()->count(5)->create(['author_id' => $author->id]);

        $augment = $this->fieldtype->augment(
            $author->posts->pluck('id')->toArray()
        );

        $this->assertIsArray($augment);
        $this->assertCount(5, $augment);

        $this->assertEquals($posts[0]->id, $augment[0]['id']->value());
        $this->assertEquals($posts[0]->title, (string) $augment[0]['title']->value());
    }

    #[Test]
    public function can_get_item_data()
    {
        // Under the hood, this tests the toItemArray method.

        $author = Author::factory()->create();
        $posts = Post::factory()->count(5)->create(['author_id' => $author->id]);

        $getItemData = $this->fieldtype->getItemData(
            $author->posts
        );

        $this->assertIsObject($getItemData);
        $this->assertTrue($getItemData instanceof Collection);

        $this->assertArrayHasKey('id', $getItemData[0]);
        $this->assertArrayHasKey('title', $getItemData[0]);
        $this->assertArrayNotHasKey('created_at', $getItemData[0]);

        $this->assertArrayHasKey('id', $getItemData[1]);
        $this->assertArrayHasKey('title', $getItemData[1]);
        $this->assertArrayNotHasKey('created_at', $getItemData[1]);
    }
}
