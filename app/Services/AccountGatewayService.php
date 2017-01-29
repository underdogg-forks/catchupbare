<?php namespace App\Services;

use App\Ninja\Repositories\AccountGatewayRepository;
use App\Ninja\Datatables\AccountGatewayDatatable;

/**
 * Class AccountGatewayService
 */
class AccountGatewayService extends BaseService
{
    /**
     * @var AccountGatewayRepository
     */
    protected $accGatewayRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * AccountGatewayService constructor.
     *
     * @param AccountGatewayRepository $accGatewayRepo
     * @param DatatableService $datatableService
     */
    public function __construct(AccountGatewayRepository $accGatewayRepo, DatatableService $datatableService)
    {
        $this->accGatewayRepo = $accGatewayRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return AccountGatewayRepository
     */
    protected function getRepo()
    {
        return $this->accGatewayRepo;
    }

    /**
     * @param $companyId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($companyId)
    {
        $query = $this->accGatewayRepo->find($companyId);

        return $this->datatableService->createDatatable(new AccountGatewayDatatable(false), $query);
    }
}
