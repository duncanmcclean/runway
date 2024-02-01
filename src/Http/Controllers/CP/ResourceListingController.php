<?php

namespace StatamicRadPack\Runway\Http\Controllers\CP;

use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Http\Requests\FilteredRequest;
use Statamic\Query\Scopes\Filters\Concerns\QueriesFilters;
use StatamicRadPack\Runway\Http\Resources\ResourceCollection;
use StatamicRadPack\Runway\Resource;

class ResourceListingController extends CpController
{
    use QueriesFilters, Traits\HasListingColumns;

    public function index(FilteredRequest $request, Resource $resource)
    {
        $blueprint = $resource->blueprint();

        if (! User::current()->can('view', $resource)) {
            abort(403);
        }

        $sortField = $request->input('sort', $resource->orderBy());
        $sortDirection = $request->input('order', $resource->orderByDirection());

        $query = $resource->model()
            ->with($resource->eagerLoadingRelationships())
            ->orderBy($sortField, $sortDirection);

        $query->when($query->hasNamedScope('runwayListing'), fn ($query) => $query->runwayListing());
        $query->when($request->search, fn ($query) => $query->runwaySearch($request->search));

        $activeFilterBadges = $this->queryFilters($query, $request->filters, [
            'resource' => $resource->handle(),
            'blueprints' => [$blueprint],
        ]);

        $results = $query->paginate($request->input('perPage', config('statamic.cp.pagination_size')));

        return (new ResourceCollection($results))
            ->setResourceHandle($resource->handle())
            ->setColumnPreferenceKey("runway.{$resource->handle()}.columns")
            ->setColumns($this->buildColumns($resource, $blueprint))
            ->additional([
                'meta' => [
                    'activeFilterBadges' => $activeFilterBadges,
                ],
            ]);
    }
}
