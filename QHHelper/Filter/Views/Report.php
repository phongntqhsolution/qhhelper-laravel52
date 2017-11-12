<?php namespace QHHelper\Filter\Views;

class Report extends DefaultView {

    function render() {
        return ["data" => $this->builder->get()];
    }
}