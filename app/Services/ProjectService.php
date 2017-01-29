<?php namespace App\Services;

use Utils;
use Auth;
use Modules\Relations\Models\Relation;
use App\Ninja\Repositories\ProjectRepository;
use App\Ninja\Datatables\ProjectDatatable;

/**
 * Class ProjectService
 */
class ProjectService extends BaseService
{
    /**
     * @var ProjectRepository
     */
    protected $projectRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * CreditService constructor.
     *
     * @param ProjectRepository $creditRepo
     * @param DatatableService $datatableService
     */
    public function __construct(ProjectRepository $projectRepo, DatatableService $datatableService)
    {
        $this->projectRepo = $projectRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return CreditRepository
     */
    protected function getRepo()
    {
        return $this->projectRepo;
    }

    /**
     * @param $data
     * @return mixed|null
     */
    public function save($data, $project = false)
    {
        if (isset($data['relation_id']) && $data['relation_id']) {
            $data['relation_id'] = Relation::getPrivateId($data['relation_id']);
        }

        return $this->projectRepo->save($data, $project);
    }

    /**
     * @param $relationPublicId
     * @param $search
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($search, $userId)
    {
        // we don't support bulk edit and hide the relation on the individual relation page
        $datatable = new ProjectDatatable();

        $query = $this->projectRepo->find($search, $userId);

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
