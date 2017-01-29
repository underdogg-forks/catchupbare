<?php
namespace Modules\Products\Http\Controllers;

use Auth;
use URL;
use View;
use Utils;
use Input;
use Session;
use Redirect;
use Modules\Products\Models\Product;
use Modules\Taxes\Models\TaxRate;
use Modules\Products\Services\ProductService;
use Modules\Products\Datatables\ProductDatatable;
use App\Http\Controllers\BaseController;

/**
 * Class ProductController
 */
class ProductController extends BaseController
{
    /**
     * @var ProductService
     */
    protected $productService;

    /**
     * ProductController constructor.
     *
     * @param ProductService $productService
     */
    public function __construct(ProductService $productService)
    {
        //parent::__construct();

        $this->productService = $productService;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_PRODUCT,
            'datatable' => new ProductDatatable(),
            'title' => trans('texts.products'),
            'statuses' => Product::getStatuses(),
        ]);
    }

    public function show($Id)
    {
        Session::reflash();

        return Redirect::to("products/$Id/edit");
    }


    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable()
    {
        return $this->productService->getDatatable(Auth::user()->company_id, Input::get('sSearch'));
    }

    /**
     * @param $Id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit($Id)
    {
        $company = Auth::user()->company;
        $product = Product::scope($Id)->withTrashed()->firstOrFail();

        $data = [
          'company' => $company,
          'taxRates' => $company->invoice_item_taxes ? TaxRate::scope()->whereIsInclusive(false)->get(['id', 'name', 'rate']) : null,
          'product' => $product,
          'entity' => $product,
          'method' => 'PUT',
          'url' => 'products/'.$Id,
          'title' => trans('texts.edit_product'),
        ];

        return View::make('companies.product', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function create()
    {
        $company = Auth::user()->company;

        $data = [
          'company' => $company,
          'taxRates' => $company->invoice_item_taxes ? TaxRate::scope()->whereIsInclusive(false)->get(['id', 'name', 'rate']) : null,
          'product' => null,
          'method' => 'POST',
          'url' => 'products',
          'title' => trans('texts.create_product'),
        ];

        return View::make('companies.product', $data);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store()
    {
        return $this->save();
    }

    /**
     * @param $Id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update($Id)
    {
        return $this->save($Id);
    }

    /**
     * @param bool $id
     * @return \Illuminate\Http\RedirectResponse
     */
    private function save($id = false)
    {
        if ($id) {
            $product = Product::scope($id)->withTrashed()->firstOrFail();
        } else {
            $product = Product::createNew();
        }

        $product->product_key = trim(Input::get('product_key'));
        $product->notes = trim(Input::get('notes'));
        $product->cost = trim(Input::get('cost'));
        $product->default_tax_rate_id = Input::get('default_tax_rate_id');

        $product->save();

        $message = $id ? trans('texts.updated_product') : trans('texts.created_product');
        Session::flash('message', $message);

        return Redirect::to("products/{$product->public_id}/edit");
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->productService->bulk($ids, $action);

        $message = Utils::pluralize($action.'d_product', $count);
        Session::flash('message', $message);

        return $this->returnBulk(ENTITY_PRODUCT, $action, $ids);
    }
}
