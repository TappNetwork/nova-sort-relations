<?php

namespace LifeOnScreen\SortRelations;

use Illuminate\Support\Str;

/**
 * Trait SortRelations
 * @package LifeOnScreen\SortRelations
 */
trait SortRelations
{
    /**
     * Get the sortable columns for the resource.
     *
     * @return array
     */
    public static function sortableRelations(): array
    {
        return static::$sortRelations ?? [];
    }

    /**
     * Get the sortable custom columns for the resource.
     *
     * @return array
     */
    public static function sortableRelationCustomColumns(): array
    {
        return static::$sortRelationCustomColumns ?? [];
    }

    /**
     * Apply any applicable orderings to the query.
     *
     * @param  string $column
     * @param  string $direction
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected static function applyRelationOrderings(string $column, string $direction, $query)
    {
        $sortRelations = static::sortableRelations();
        $sortRelationCustomColumns = static::sortableRelationCustomColumns();

        $model = $query->getModel();
        $relation = $column;
        $related = $model->{$column}()->getRelated();
        
        if (isset($sortRelationCustomColumns[$column])) {
            $foreignKey = $sortRelationCustomColumns[$column];
        } else {
            $foreignKey = Str::snake($relation) . '_' . $related->getKeyName();
        }

        $query->select($model->getTable() . '.*');
        $query->leftJoin($related->getTable(), $model->qualifyColumn($foreignKey), '=', $related->qualifyColumn($related->getKeyName()));

        if (is_string($sortRelations[$column])) {
            $qualified = $related->qualifyColumn($sortRelations[$column]);
            $query->orderByRaw($qualified . ' IS NULL');
            $query->orderBy($qualified, $direction);
        }

        if (is_array($sortRelations[$column])) {
            foreach ($sortRelations[$column] as $orderColumn) {
                $query->orderByRaw($related->qualifyColumn($orderColumn) . ' IS NULL');
                $query->orderBy($related->qualifyColumn($orderColumn), $direction);
            }
        }

        return $query;
    }

    /**
     * Apply any applicable orderings to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  array $orderings
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected static function applyOrderings($query, array $orderings)
    {
        if (empty($orderings)) {
            return empty($query->orders)
                ? $query->latest($query->getModel()->getQualifiedKeyName())
                : $query;
        }

        $sortRelations = static::sortableRelations();
        $sortRelationCustomColumns = static::sortableRelationCustomColumns();

        foreach ($orderings as $column => $direction) {
            if (empty($direction)) {
                $direction = 'asc';
            }

            $sortColumn = $column;

            $customRelation = array_search($column, $sortRelationCustomColumns);
            
            if (! $customRelation && Str::endsWith($column, '_id')) {
                $column = Str::before($column, '_id');
            }

            /*
             * When column name ends with _id and it's not a relationship column
             * Use original name which ends with _id.
             */
            if (! array_key_exists(Str::camel($column), $sortRelations)) {
                $column = $sortColumn;
            }

            if ($customRelation) {
                $query = self::applyRelationOrderings($customRelation, $direction, $query);
            } elseif (array_key_exists(Str::camel($column), $sortRelations)) {
                $query = self::applyRelationOrderings(Str::camel($column), $direction, $query);
            } else {
                $query->orderBy($column, $direction);
            }
        }

        return $query;
    }
}
