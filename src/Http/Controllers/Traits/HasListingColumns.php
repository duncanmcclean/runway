<?php

namespace DuncanMcClean\Runway\Http\Controllers\Traits;

use DuncanMcClean\Runway\Resource;
use Statamic\Facades\User;

trait HasListingColumns
{
    protected function buildColumns(Resource $resource, $blueprint)
    {
        $preferredFirstColumn = isset(User::current()->preferences()['runway'][$resource->handle()]['columns'])
            ? User::current()->preferences()['runway'][$resource->handle()]['columns'][0]
            : $resource->titleField();

        return collect($resource->listableColumns())
            ->map(function ($columnKey) use ($blueprint, $preferredFirstColumn) {
                $field = $blueprint->field($columnKey);

                return [
                    'handle' => $columnKey,
                    'title' => $field
                        ? $field->display()
                        : $field,
                    'has_link' => $preferredFirstColumn === $columnKey,
                    'is_primary_column' => $preferredFirstColumn === $columnKey,
                ];
            })
            ->toArray();
    }
}
