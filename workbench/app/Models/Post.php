<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Statamic\Facades\Blink;
use StatamicRadPack\Runway\Routing\Traits\RunwayRoutes;
use StatamicRadPack\Runway\Traits\HasRunwayResource;
use Workbench\Database\Factories\PostFactory;

class Post extends Model
{
    use HasFactory, HasRunwayResource, RunwayRoutes;

    protected $fillable = [
        'title', 'slug', 'body', 'values', 'external_links', 'author_id', 'sort_order', 'published', 'mutated_value',
    ];

    protected $appends = [
        'appended_value',
        'excerpt',
    ];

    protected $casts = [
        'values' => 'array',
        'external_links' => 'object',
        'published' => 'boolean',
    ];

    public function scopeFood($query)
    {
        $query->whereIn('title', ['Pasta', 'Apple', 'Burger']);
    }

    public function scopeFruit($query, $smth)
    {
        if ($smth === 'idoo') {
            $query->whereIn('title', ['Apple']);
        }
    }

    public function scopeRunwayListing($query)
    {
        if ($params = Blink::get('RunwayListingScopeOrderBy')) {
            $query->orderBy($params[0], $params[1]);
        }
    }

    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    public function excerpt(): Attribute
    {
        return Attribute::make(
            get: function () {
                return 'This is an excerpt.';
            }
        );
    }

    public function appendedValue(): Attribute
    {
        return Attribute::make(
            get: function () {
                return 'This is an appended value.';
            }
        );
    }

    public function mutatedValue(): Attribute
    {
        return Attribute::make(
            get: fn (string $value, array $attributes) => "{$value} is a mutated value.",
        );
    }

    public function searchMethod()
    {
        return 'This is a value returned from a method';
    }

    protected static function newFactory()
    {
        return PostFactory::new();
    }
}
