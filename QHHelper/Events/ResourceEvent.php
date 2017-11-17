<?php namespace QHHelper\Events;

class ResourceEvent {
    public $model;
    public $oldModel;
    public $resource;

    function __construct($resource, $model, $oldModel = null) {
        $this->model = $model;
        $this->oldModel = $oldModel;
        $this->resource = $resource;
    }
}