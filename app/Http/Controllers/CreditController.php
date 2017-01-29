<?php namespace App\Http\Controllers;

use Input;
use Redirect;
use Session;
use URL;
use Utils;
use View;
use Modules\Relations\Models\Relation;
use App\Models\Credit;
use App\Services\CreditService;
use App\Ninja\Repositories\CreditRepository;
use App\Http\Requests\UpdateCreditRequest;
use App\Http\Requests\CreateCreditRequest;
use App\Http\Requests\CreditRequest;
use App\Ninja\Datatables\CreditDatatable;

class CreditController extends BaseController
{
    protected $creditRepo;
    protected $creditService;
    protected $entityType = ENTITY_CREDIT;

    public function __construct(CreditRepository $creditRepo, CreditService $creditService)
    {
        // parent::__construct();

        $this->creditRepo = $creditRepo;
        $this->creditService = $creditService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_CREDIT,
            'datatable' => new CreditDatatable(),
            'title' => trans('texts.credits'),
        ]);
    }

    public function getDatatable($relationPublicId = null)
    {
        return $this->creditService->getDatatable($relationPublicId, Input::get('sSearch'));
    }

    public function create(CreditRequest $request)
    {
        $data = [
            'relationPublicId' => Input::old('relation') ? Input::old('relation') : ($request->relation_id ?: 0),
            'credit' => null,
            'method' => 'POST',
            'url' => 'credits',
            'title' => trans('texts.new_credit'),
            'relations' => Relation::scope()->with('contacts')->orderBy('name')->get(),
        ];

        return View::make('credits.edit', $data);
    }

    public function edit($publicId)
    {
        $credit = Credit::withTrashed()->scope($publicId)->firstOrFail();

        $this->authorize('edit', $credit);

        $credit->credit_date = Utils::fromSqlDate($credit->credit_date);

        $data = array(
            'relation' => $credit->relation,
            'relationPublicId' => $credit->relation->id,
            'credit' => $credit,
            'method' => 'PUT',
            'url' => 'credits/'.$publicId,
            'title' => 'Edit Credit',
            'relations' => null,
        );

        return View::make('credits.edit', $data);
    }

    public function update(UpdateCreditRequest $request)
    {
        $credit = $request->entity();

        return $this->save($credit);
    }

    public function store(CreateCreditRequest $request)
    {
        return $this->save();
    }

    private function save($credit = null)
    {
        $credit = $this->creditService->save(Input::all(), $credit);

        $message = $credit->wasRecentlyCreated ? trans('texts.created_credit') : trans('texts.updated_credit');
        Session::flash('message', $message);

        return redirect()->to("relations/{$credit->relation->id}#credits");
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->creditService->bulk($ids, $action);

        if ($count > 0) {
            $message = Utils::pluralize($action.'d_credit', $count);
            Session::flash('message', $message);
        }

        return $this->returnBulk(ENTITY_CREDIT, $action, $ids);
    }
}
