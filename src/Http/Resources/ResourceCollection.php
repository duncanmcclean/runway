<?php

namespace StatamicRadPack\Runway\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection as LaravelResourceCollection;
use Statamic\Facades\Action;
use Statamic\Facades\User;
use Statamic\Http\Resources\CP\Concerns\HasRequestedColumns;
use StatamicRadPack\Runway\Fieldtypes\BelongsToFieldtype;
use StatamicRadPack\Runway\Fieldtypes\HasManyFieldtype;
use StatamicRadPack\Runway\Runway;

class ResourceCollection extends LaravelResourceCollection
{
    use HasRequestedColumns;

    public $collects;

    public $columns;

    protected $resourceHandle;

    protected $runwayResource;

    protected $columnPreferenceKey;

    public function setColumnPreferenceKey($key): self
    {
        $this->columnPreferenceKey = $key;

        return $this;
    }

    public function setColumns(): self
    {
        $columns = $this->runwayResource->blueprint()->columns();

        if ($key = $this->columnPreferenceKey) {
            $columns->setPreferred($key);
        }

        $this->columns = $columns->rejectUnlisted()->values();

        return $this;
    }

    public function setResourceHandle($handle): self
    {
        $this->runwayResource = Runway::findResource($handle);
        $this->resourceHandle = $handle;

        return $this;
    }

    public function toArray($request): array
    {
        $this->setColumns();
        $columns = $this->columns->pluck('field')->toArray();

        $handle = $this->resourceHandle;

        return [
            'data' => $this->collection->map(function ($model) use ($handle) {
                $row = $model->toArray();

                foreach ($row as $key => $value) {
                    if (! $this->requestedColumns()->contains('field', $key)) {
                        unset($row[$key]);

                        continue;
                    }

                    if ($this->runwayResource->blueprint()->hasField($key)) {
                        // If we've eager loaded in relationships, just pass in the model
                        // instance. We can prevent extra queries this way.
                        if ($this->runwayResource->blueprint()->field($key)->fieldtype() instanceof BelongsToFieldtype) {
                            $relationName = $this->runwayResource->eloquentRelationships()->get($key);

                            if ($model->relationLoaded($relationName)) {
                                $value = $model->$relationName;
                            }
                        }

                        if ($this->runwayResource->blueprint()->field($key)->fieldtype() instanceof HasManyFieldtype) {
                            $relationName = $key;

                            if ($model->relationLoaded($relationName)) {
                                $value = $model->$relationName;
                            }
                        }

                        $row[$key] = $this->runwayResource->blueprint()->field($key)->setValue($value)->preProcessIndex()->value();
                    }
                }

                foreach ($this->runwayResource->blueprint()->fields()->except(array_keys($row))->all() as $fieldHandle => $field) {
                    $key = str_replace('->', '.', $fieldHandle);

                    $row[$fieldHandle] = $field->setValue(data_get($model, $key))->preProcessIndex()->value();
                }

                $row['id'] = $model->getKey();
                $row['edit_url'] = cp_route('runway.edit', ['resource' => $handle, 'model' => $model->getRouteKey()]);
                $row['permalink'] = $this->runwayResource->hasRouting() ? $model->uri() : null;
                $row['editable'] = User::current()->can('edit', [$this->runwayResource, $model]);
                $row['viewable'] = User::current()->can('view', [$this->runwayResource, $model]);
                $row['actions'] = Action::for($model, ['resource' => $this->runwayResource->handle()]);

                return $row;
            }),
        ];
    }

    public function with($request)
    {
        return [
            'meta' => [
                'columns' => $this->visibleColumns(),
            ],
        ];
    }
}
