<?php

declare(strict_types=1);

namespace Criba\Infrastructure\Eloquent;

use Criba\Comparison;
use Criba\Condition;
use Criba\Criteria;
use Criba\Filter;
use Criba\Infrastructure\Eloquent\Exception\EloquentCriteriaException;
use Criba\OrderBy;
use Criba\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EloquentCriteriaBuilder
{
    public function __construct(
        public readonly string $model
    ) {
        if (! class_exists($model)) {
            throw new EloquentCriteriaException('The model class does not exist');
        }

        if (! is_subclass_of($model, Model::class)) {
            throw new EloquentCriteriaException('The model class does not extend Eloquent\'s model class');
        }
    }

    public function query(Criteria $criteria): Builder
    {
        $query = $this->model::query();

        self::filter($query, $criteria->filter);
        self::sort($query, $criteria->orderBy);
        self::page($query, $criteria->page);

        return $query;
    }

    private static function filter(Builder $query, Filter $filter, string $where = 'where'): Builder
    {
        if ($filter->parentheses) {
            return $query->$where(function (Builder $query) use ($filter) {
                self::join($query, $filter);
            });
        } else {
            return self::join($query, $filter);
        }
    }

    private static function join(Builder $query, Filter $filter): Builder
    {
        $query = self::condition($query, $filter->condition);

        if ($filter->join) {
            if ($filter->join === 'and') {
                return self::condition($query, $filter->extraCondition);
            }

            if ($filter->join === 'or') {
                return self::condition($query, $filter->extraCondition, 'orWhere');
            }
        }
    }

    private static function condition(Builder $query, Condition|Comparison|Filter $filter, string $where = 'where'): Builder
    {
        if ($filter instanceof Condition && $filter->operator !== 'in') {
            if ($filter->negate) {
                $where = $where."Not";
            }

            return $query->$where($filter->field, $filter->operator, $filter->value);
        }

        if ($filter instanceof Condition && $filter->operator === 'in') {
            if ($filter->negate) {
                $in = $where."NotIn";
            } else {
                $in = $where."In";
            }

            return $query->$in($filter->field, $filter->value);
        }

        if ($filter instanceof Comparison) {
            $comparison = $where."Column";
            return $query->$comparison($filter->field, $filter->operator, $filter->otherField);
        }

        if ($filter instanceof Filter) {
            return self::filter($query, $filter, $where);
        }
    }

    private static function sort(Builder $query, OrderBy $orderBy): Builder
    {
        foreach ($orderBy->orders as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        return $query;
    }

    private static function page(Builder $query, Page $page): Builder
    {
        if (! is_null($page->offset)) {
            $query->skip($page->offset);
        }

        if (! is_null($page->limit)) {
            $query->take($page->limit);
        }

        return $query;
    }
}
