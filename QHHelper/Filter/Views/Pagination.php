<?php namespace QHHelper\Filter\Views;

class Pagination extends DefaultView {

    function render() {
        $pagination = $this->builder->paginate(request('limit', 20));
        $pagination->appends(request()->except('page'));
        $rs = $pagination->toArray();
        $rs['links'] = (string) $pagination->links();
        $rs['total'] = $pagination->total();
        return $rs;
    }
}