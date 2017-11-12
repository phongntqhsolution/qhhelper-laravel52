<?php namespace QHHelper\Events;

class ResourceEvent {
    protected $model;
    protected $oldModel;
    protected $resource;

    function __construct($resource, $model, $oldModel = null) {
        $this->model = $model;
        $this->oldModel = $oldModel;
        $this->resource = $resource;
    }
}