<?php namespace QHHelper\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use QHHelper\Events\ResourceAfterUpdatedEvent;
use QHHelper\Events\ResourceBeforeUpdatedEvent;
use QHHelper\Filter\Filter;
use QHHelper\Events\ResourceCreatedEvent;
use QHHelper\Events\ResourceUpdatedEvent;
use QHHelper\Events\ResourceDeletedEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class BaseResourceModelController extends Controller {
    /**
     * @var Model
     */
    protected $model;
    protected $store_rule;
    protected $update_rule;

    function __construct($model, $store_rule, $update_rule) {
        $this->model = $model;
        $this->store_rule = $store_rule;
        $this->update_rule = $update_rule;
    }

    function index(Request $request) {
        $paginate = (new Filter($this->model, $request->input()))
            ->filter();

        return response()->json($paginate);
    }

    function show(Request $request, $model) {
        return response()->json(['data' => $model]);
    }

    function store(Request $request) {
        try {
            $this->validate($request, $this->store_rule);

            $input = $request->input();
            $this->model->fill($input);
            $this->model->save();
            event(new ResourceCreatedEvent(get_class($this->model), $this->model));
            return response()->json(['data' => $this->model], 201);
        } catch (ValidationException $e) {
            return response()->json(['messages' => $e->validator->messages()], 400);
        }
    }

    function update(Request $request, Model $model) {
        try {
            $this->validate($request, $this->update_rule);

            $input = $request->input();
            event(new ResourceBeforeUpdatedEvent(get_class($this->model), $model, null));
            $oldModel = clone $model;
            $model->fill($input)->save();
            event(new ResourceAfterUpdatedEvent(get_class($this->model), $model, null));
            event(new ResourceUpdatedEvent(get_class($this->model), $model, $oldModel));

            return response()->json(['data' => $model]);
        } catch (ValidationException $e) {
            return response()->json(['messages' => $e->validator->messages()], 400);
        }
    }

    function destroy(Request $request, Model $model) {
        $oldModel = clone $model;
        $model->delete();
        event(new ResourceDeletedEvent(get_class($this->model), $model, $oldModel));
        return response(null, 204);
    }
}