<?php

namespace DoubleThreeDigital\Runway\Search;

use DoubleThreeDigital\Runway\Runway;
use Illuminate\Support\Collection;
use Statamic\Search\Searchables\Provider as BaseProvider;
use Statamic\Support\Str;

class Provider extends BaseProvider
{
    protected static $handle = 'runway';

    protected static $referencePrefix = 'runway';

    public function provide(): Collection
    {
        $resources = $this->usesWildcard() ? Runway::allResources()->keys() : $this->keys;

        return collect($resources)->flatMap(function ($handle) {
            return Runway::findResource($handle)->model()->all()->mapInto(Searchable::class);
        })->filter($this->filter());
    }

    public function contains($searchable): bool
    {
        if (! $searchable instanceof Searchable) {
            return false;
        }

        $resource = $searchable->resource();

        if (! $this->usesWildcard() && ! in_array($resource->handle(), $this->keys)) {
            return false;
        }

        return $this->filter()($searchable);
    }

    public function find(array $keys): Collection
    {
        return collect($keys)
            ->groupBy(fn ($key) => Str::before($key, '::'))
            ->flatMap(function ($items, $handle) {
                $ids = $items->map(fn ($item) => Str::after($item, '::'));

                return Runway::findResource($handle)->model()->find($ids);
            })->mapInto(Searchable::class);
    }
}
