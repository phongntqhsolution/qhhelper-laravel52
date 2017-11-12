<?php namespace QHHelper\Filter\Views;

class DefaultView {
    public $builder;

    function __construct($builder) {
        $this->builder = $builder;
    }

    function render() {
        $pagination = $this->builder->simplePaginate(request('limit', 20));
        $pagination->appends(request()->except('page'));
        return $pagination;
    }
}