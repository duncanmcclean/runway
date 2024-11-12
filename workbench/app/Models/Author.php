<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Statamic\Facades\Blink;
use StatamicRadPack\Runway\Traits\HasRunwayResource;
use Workbench\Database\Factories\AuthorFactory;

class Author extends Model
{
    use HasFactory, HasRunwayResource;

    protected $fillable = [
        'name', 'start_date', 'end_date',
    ];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function pivottedPosts()
    {
        return $this->belongsToMany(Post::class, 'post_author');
    }

    public function scopeRunwayListing($query)
    {
        if ($params = Blink::get('RunwayListingScopeOrderBy')) {
            $query->orderBy($params[0], $params[1]);
        }
    }

    protected static function newFactory()
    {
        return AuthorFactory::new();
    }
}
