<?php
namespace Modules\Products\Services;

use Auth;
use Utils;
use Modules\Products\Repositories\ProductRepository;
use Modules\Products\Datatables\ProductDatatable;
use App\Services\BaseService;
use App\Services\DatatableService;


class ProductService extends BaseService
{
    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * @var ProductRepository
     */
    protected $productRepo;

    /**
     * ProductService constructor.
     *
     * @param DatatableService $datatableService
     * @param ProductRepository $productRepo
     */
    public function __construct(DatatableService $datatableService, ProductRepository $productRepo)
    {
        $this->datatableService = $datatableService;
        $this->productRepo = $productRepo;
    }

    /**
     * @return ProductRepository
     */
    protected function getRepo()
    {
        return $this->productRepo;
    }

    /**
     * @param $companyId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($companyId, $search)
    {
        $datatable = new ProductDatatable(true);
        $query = $this->productRepo->find($companyId, $search);

        if(!Utils::hasPermission('view_all')){
            $query->where('products.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
