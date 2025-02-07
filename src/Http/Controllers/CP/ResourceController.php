<?php

namespace StatamicRadPack\Runway\Http\Controllers\CP;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Statamic\CP\Breadcrumbs;
use Statamic\CP\Column;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Action;
use Statamic\Facades\Scope;
use Statamic\Facades\User;
use Statamic\Fields\Field;
use Statamic\Http\Controllers\CP\CpController;
use StatamicRadPack\Runway\Fieldtypes\BelongsToFieldtype;
use StatamicRadPack\Runway\Fieldtypes\HasManyFieldtype;
use StatamicRadPack\Runway\Http\Requests\CP\CreateRequest;
use StatamicRadPack\Runway\Http\Requests\CP\EditRequest;
use StatamicRadPack\Runway\Http\Requests\CP\IndexRequest;
use StatamicRadPack\Runway\Http\Requests\CP\StoreRequest;
use StatamicRadPack\Runway\Http\Requests\CP\UpdateRequest;
use StatamicRadPack\Runway\Http\Resources\CP\Model as ModelResource;
use StatamicRadPack\Runway\Relationships;
use StatamicRadPack\Runway\Resource;

class ResourceController extends CpController
{
    use Traits\ExtractsFromModelFields, Traits\HasListingColumns, Traits\PreparesModels;

    public function index(IndexRequest $request, Resource $resource)
    {
        return view('runway::index', [
            'resource' => $resource,
            'canCreate' => User::current()->can('create', $resource)
                && $resource->hasVisibleBlueprint()
                && ! $resource->readOnly(),
            'createUrl' => cp_route('runway.create', ['resource' => $resource->handle()]),
            'createLabel' => __('Create :resource', ['resource' => $resource->singular()]),
            'columns' => $resource->blueprint()->columns()
                ->when($resource->hasPublishStates(), function ($collection) {
                    $collection->put('status', Column::make('status')
                        ->listable(true)
                        ->visible(true)
                        ->defaultVisibility(true)
                        ->sortable(false));
                })
                ->setPreferred("runway.{$resource->handle()}.columns")
                ->rejectUnlisted()
                ->values(),
            'filters' => Scope::filters('runway', ['resource' => $resource->handle()]),
            'actionUrl' => cp_route('runway.models.actions.run', ['resource' => $resource->handle()]),
            'primaryColumn' => $this->getPrimaryColumn($resource),
            'actions' => Action::for($resource, ['view' => 'form']),
        ]);
    }

    public function create(CreateRequest $request, Resource $resource)
    {
        $blueprint = $resource->blueprint();
        $fields = $blueprint->fields();
        $fields = $fields->preProcess();

        $viewData = [
            'title' => __('Create :resource', ['resource' => $resource->singular()]),
            'breadcrumbs' => new Breadcrumbs([[
                'text' => $resource->plural(),
                'url' => cp_route('runway.index', [
                    'resource' => $resource->handle(),
                ]),
            ]]),
            'actions' => [
                'save' => cp_route('runway.store', ['resource' => $resource->handle()]),
            ],
            'resource' => $request->wantsJson() ? $resource->toArray() : $resource,
            'blueprint' => $blueprint->toPublishArray(),
            'values' => $fields->values()->merge([
                $resource->publishedColumn() => true,
            ])->all(),
            'meta' => $fields->meta(),
            'resourceHasRoutes' => $resource->hasRouting(),
            'canManagePublishState' => User::current()->can('publish', $resource),
        ];

        if ($request->wantsJson()) {
            return $viewData;
        }

        return view('runway::create', $viewData);
    }

    public function store(StoreRequest $request, Resource $resource)
    {
        $resource
            ->blueprint()
            ->fields()
            ->addValues($request->all())
            ->validator()
            ->validate();

        $model = $resource->model();

        $this->prepareModelForSaving($resource, $model, $request);

        if ($resource->revisionsEnabled()) {
            $saved = $model->store([
                'message' => $request->message,
                'user' => User::current(),
            ]);
        } else {
            $saved = DB::transaction(function () use ($model, $request) {
                $model->save();
                Relationships::for($model)->with($request->all())->save();

                return true;
            });
        }

        return [
            'data' => (new ModelResource($model->fresh()))->resolve()['data'],
            'saved' => $saved,
        ];
    }

    public function edit(EditRequest $request, Resource $resource, $model)
    {
        $model = $resource->model()->where($resource->model()->qualifyColumn($resource->routeKey()), $model)->first();

        if (! $model) {
            throw new NotFoundHttpException;
        }

        $model = $model->fromWorkingCopy();

        $blueprint = $resource->blueprint();

        [$values, $meta] = $this->extractFromFields($model, $resource, $blueprint);

        $viewData = [
            'title' => $model->getAttribute($resource->titleField()),
            'reference' => $model->reference(),
            'method' => 'patch',
            'breadcrumbs' => new Breadcrumbs([[
                'text' => $resource->plural(),
                'url' => cp_route('runway.index', [
                    'resource' => $resource->handle(),
                ]),
            ]]),
            'resource' => $resource,
            'actions' => [
                'save' => $model->runwayUpdateUrl(),
                'publish' => $model->runwayPublishUrl(),
                'unpublish' => $model->runwayUnpublishUrl(),
                'revisions' => $model->runwayRevisionsUrl(),
                'restore' => $model->runwayRestoreRevisionUrl(),
                'createRevision' => $model->runwayCreateRevisionUrl(),
                'editBlueprint' => cp_route('blueprints.edit', ['namespace' => 'runway', 'handle' => $resource->handle()]),
            ],
            'blueprint' => $blueprint->toPublishArray(),
            'values' => $values,
            'meta' => $meta,
            'readOnly' => $resource->readOnly(),
            'status' => $model->publishedStatus(),
            'permalink' => $resource->hasRouting() ? $model->uri() : null,
            'resourceHasRoutes' => $resource->hasRouting(),
            'currentModel' => [
                'id' => $model->getKey(),
                'reference' => $model->reference(),
                'title' => $model->{$resource->titleField()},
                'edit_url' => $request->url(),
            ],
            'canManagePublishState' => User::current()->can('publish', $resource),
            'itemActions' => Action::for($model, ['resource' => $resource->handle(), 'view' => 'form']),
            'revisionsEnabled' => $resource->revisionsEnabled(),
            'hasWorkingCopy' => $model->hasWorkingCopy(),
        ];

        if ($request->wantsJson()) {
            return $viewData;
        }

        return view('runway::edit', $viewData);
    }

    public function update(UpdateRequest $request, Resource $resource, $model)
    {
        $resource->blueprint()->fields()->setParent($model)->addValues($request->all())->validator()->validate();

        $model = $resource->model()->where($resource->model()->qualifyColumn($resource->routeKey()), $model)->first();
        $model = $model->fromWorkingCopy();

        $this->prepareModelForSaving($resource, $model, $request);

        if ($resource->revisionsEnabled() && $model->published()) {
            $saved = $model
                ->makeWorkingCopy()
                ->user(User::current())
                ->save();

            $model = $model->fromWorkingCopy();

            $this->saveNonRevisableFields($resource, $model);
        } else {
            $saved = DB::transaction(function () use ($model, $request) {
                $model->save();
                Relationships::for($model)->with($request->all())->save();

                return true;
            });

            $model->refresh();
        }

        [$values] = $this->extractFromFields($model, $resource, $resource->blueprint());

        return [
            'data' => array_merge((new ModelResource($model))->resolve()['data'], [
                'values' => $values,
            ]),
            'saved' => $saved,
        ];
    }

    private function saveNonRevisableFields(Resource $resource, Model $model): void
    {
        $dbVersion = $model->fresh();

        $fields = $resource->blueprint()->fields()->all()
            ->reject(fn (Field $field) => $field->isRevisable())
            ->reject(fn (Field $field) => $field->fieldtype() instanceof BelongsToFieldtype)
            ->reject(fn (Field $field) => $field->fieldtype() instanceof HasManyFieldtype);

        if ($fields->isNotEmpty()) {
            $fields->each(fn ($ignore, string $fieldHandle) => $dbVersion->setAttribute($fieldHandle, $model->{$fieldHandle}));

            $dbVersion->save();
        }
    }
}
