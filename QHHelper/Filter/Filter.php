<?php

namespace QHHelper\Filter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Log;
use DB;

class Filter
{
    protected $builder;

    protected $attributes;

    protected $stringFields = [];

    protected $fillable = [];

    protected $limit = 20;
    
    protected $orderBy = [];

    protected $betweenValue = [];

    public function __construct($builder, array $attributes = [])
    {
        $this->builder = $builder;
        $this->attributes = $attributes;
        //remove token
        unset($this->attributes['token']);
        $this->fillable = $this->getModel()->getFillable();
    }

    public function getModel()
    {
        if($this->builder instanceof Builder) {
            return $this->builder->getModel();
        }
        if($this->builder instanceof Relation) {
            return $this->builder->getRelated();
        }
        return $this->builder;
    }

    public function getBuilder() {
        return $this->builder;
    }

    public function getStringField()
    {
        return $this->stringFields;
    }

    public function setStringField($array = [])
    {
        $this->stringFields = $array;
        return $this;
    }

    public function getOrderBy()
    {
        return $this->orderBy;
    }

    public function setOrderBy($value)
    {
        $this->orderBy = $value;
        return $this;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function limit() {
//        $this->limit =
    }

    public function select()
    {
        if(empty($this->attributes['select'])) {
            return $this;
        }

        $select = $this->attributes['select'];
        $results = array_intersect($this->getModel()->getFillable(), $select);
        $this->builder = $this->builder->select($results);
        unset($this->attributes['select']);
        return $this;
    }

    public function search()
    {
        if(empty($this->attributes['s'])) {
            return $this;
        }

        foreach($this->attributes['s'] as $key => $param) {
//            if(in_array($key, $this->stringFields)) {
            if(in_array($key, $this->fillable)) {
                $this->builder = $this->builder
                    ->where($key, 'LIKE', '%' . $param . '%');
                continue;
            }
        }

        unset($this->attributes['s']);

        return $this;
    }

    public function whereHas() {
        if(isset($this->attributes['has']))
            foreach($this->attributes['has'] as $key => $val) {
                if(is_string($val) && method_exists($this->getModel(), $val)) {
                    $this->builder = $this->builder->has($val);
                    continue;
                }

                if(is_array($val) && isset($val['operator']) && isset($val['value']) ) {
                    if(method_exists($this->getModel(), $key)) {
                        $this->builder = $this->builder->has($this->getModel()->getTable() . '.' . $key, $val['operator'], $val['value']);
                        continue;
                    }
                }
            }

        if(isset($this->attributes['where_has']))
            foreach($this->attributes['where_has'] as $relation => $where) {
                if(!is_array($where)) continue;
                if(!method_exists($this->getModel(), $relation)) continue;

                $this->builder = $this->builder->whereHas($relation, function($q) use ($where) {
                    $q->where($where);
                });
            }

        if(isset($this->attributes['where_has_in'])) {
            foreach($this->attributes['where_has_in'] as $relation => $where) {
                if(!is_array($where)) continue;
                if(!method_exists($this->getModel(), $relation)) continue;

                $this->builder = $this->builder->whereHas($relation, function($q) use ($where) {
                    array_walk($where, function($val, $key) use (&$q){
                        if(is_array($val))
                            $q->whereIn($this->getModel()->getTable() . '.' . $key, $val);
                        else {
                            $q->whereIn($this->getModel()->getTable() . '.' . $key, explode(",", $val));
                        }

                    });
                });
            }
        }

        unset($this->attributes['where_has']);
        unset($this->attributes['has']);

        return $this;
    }

    public function orderBy()
    {
        if(empty($this->attributes['order_by'])) return $this;

        foreach($this->attributes['order_by'] as $sort => $order) {
            if(! in_array($sort, $this->fillable) || !in_array($order, ['desc', 'asc'])) continue;

            $this->builder = $this->builder->orderBy($sort, $order);
        }

        return $this;
    }
    
    public function with()
    {
        if(!isset($this->attributes['with'])) return $this;
        if(empty($this->attributes['with']) || $this->builder->count() == 0) {
            return $this;
        }
        foreach($this->attributes['with'] as $with) {
            $this->builder = $this->call_with($with);
        }
        unset($this->attributes['with']);

        return $this;
    }

    public function call_with($relation)
    {
        if(empty($relation) || !method_exists($this->getModel(), $relation)) {
            return $this->builder;
        }
        return $this->builder->with($relation);
    }

    public function between()
    {
        if (empty($this->attributes['between'])) {
            return $this;
        }
        foreach($this->attributes['between'] as $key => $between) {
            if(empty($between['from']) || empty($between['to'])) {
                continue;
            }

            if(gettype($between['from']) != gettype($between['to'])) {
                continue;
            }

            if($between['from'] > $between['to']) {
                continue;
            }
            $this->builder = $this->builder->whereBetween($this->getModel()->getTable() . '.' . $key, [
                $between['from'],
                $between['to']
            ]);
        }
        unset($this->attributes['between']);
        return $this;
    }

    public function selectRaw() {
        if(empty($this->attributes['select_raw'])) {
            return $this;
        }
        $this->builder = $this->builder->select(DB::raw($this->attributes['select_raw']));

        unset($this->attributes['select_raw']);
        return $this;
    }

    public function whereRaw() {
        if(empty($this->attributes['where_raw'])) {
            return $this;
        }
        $this->builder = $this->builder->whereRaw($this->attributes['where_raw']);

        unset($this->attributes['where_raw']);
        return $this;
    }

    public function where() {
        foreach($this->attributes as $key => $val) {
            if(! in_array($key, $this->fillable) || is_array($val)) continue;
            if($val == 'none' || $val == 'all' || is_null($val) || !isset($val)) continue;
            if(preg_match('/\,/', $val)) {
                $val = explode(',', $val);
                $this->builder = $this->builder->whereIn($this->getModel()->getTable() . '.' . $key , $val);
                continue;
            }
            $this->builder = $this->builder->where($this->getModel()->getTable() . '.' . $key, $val);
        }
        return $this;
    }

    public function groupBy() {
        if(empty($this->attributes['group_by'])) {
            return $this;
        }
        $this->builder = $this->builder->groupBy($this->attributes['group_by']);
        unset($this->attributes['group_by']);
        return $this;
    }

    public function groupByRaw() {
        if(empty($this->attributes['group_by_raw'])) {
            return $this;
        }
        $this->builder = $this->builder->groupBy(DB::raw($this->attributes['group_by_raw']));
        unset($this->attributes['group_by_raw']);
        return $this;
    }

    public function havingRaw() {
        if(empty($this->attributes['having_raw'])) {
            return $this;
        }
        $this->builder = $this->builder->havingRaw(DB::raw($this->attributes['having_raw']));
        unset($this->attributes['having_raw']);
        return $this;
    }

    public function join() {
        if(empty($this->attributes['join']) || 
            empty($this->attributes['join']['table']) ||
            empty($this->attributes['join']['field_relation']) || 
            empty($this->attributes['join']['field_origin'])
        ) {
            return $this;
        }
        $this->builder = $this->builder->join($this->attributes['join']['table'],
            $this->attributes['join']['table'] . '.' . $this->attributes['join']['field_relation'], 
            '=',
            $this->getModel()->getTable() . '.' . $this->attributes['join']['field_origin']
        )->select($this->getModel()->getTable() . '.*');
            
        if(!empty($this->attributes['join']['order'])
            && is_array($this->attributes['join']['order'])
        ) {
            foreach($this->attributes['join']['order'] as $key => $order) {
                $this->builder =  $this->builder
                    ->orderBy($this->attributes['join']['table'] . '.' . $key, $order);
            }   
        }
        unset($this->attributes['join']);
        return $this;
    }

    public function filter()
    {
        $viewEngine = isset($this->attributes['view']) ? $this->attributes['view'] : "default_view";
        unset($this->attributes['view']);

        $viewEngine = ucfirst(camel_case($viewEngine));
        $class = "QHHelper\\Filter\\Views\\{$viewEngine}";

        if(!class_exists($class))
            $class = "QHHelper\\Filter\\Views\\DefaultView";

        $this->select()
            ->selectRaw()
            ->search()
            ->between()
            ->with()
            ->orderBy()
            ->groupBy()
            ->havingRaw()
            ->whereHas()
            ->whereRaw()
            ->where()
            ->join();

        $view = new $class($this->builder);

        return $view->render();
    }
}