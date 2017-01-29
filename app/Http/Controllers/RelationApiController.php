<?php namespace App\Http\Controllers;

use App\Http\Requests\RelationRequest;
use Response;
use Input;
use App\Models\Relation;
use App\Ninja\Repositories\RelationRepository;
use App\Http\Requests\CreateRelationRequest;
use App\Http\Requests\UpdateRelationRequest;

class ClientApiController extends BaseAPIController
{
    protected $clientRepo;

    protected $entityType = ENTITY_RELATION;

    public function __construct(RelationRepository $clientRepo)
    {
        parent::__construct();

        $this->clientRepo = $clientRepo;
    }

    /**
     * @SWG\Get(
     *   path="/relations",
     *   summary="List of relations",
     *   tags={"relation"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with relations",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $relations = Relation::scope()
            ->orderBy('created_at', 'desc')
            ->withTrashed();

        // Filter by email
        if ($email = Input::get('email')) {
            $relations = $relations->whereHas('contacts', function ($query) use ($email) {
                $query->where('email', $email);
            });
        }

        return $this->listResponse($relations);
    }

    /**
     * @SWG\Get(
     *   path="/relations/{relation_id}",
     *   summary="Individual Relation",
     *   tags={"relation"},
     *   @SWG\Response(
     *     response=200,
     *     description="A single relation",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function show(RelationRequest $request)
    {
        return $this->itemResponse($request->entity());
    }




    /**
     * @SWG\Post(
     *   path="/relations",
     *   tags={"relation"},
     *   summary="Create a relation",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Relation")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New relation",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateRelationRequest $request)
    {
        $client = $this->clientRepo->save($request->input());

        return $this->itemResponse($client);
    }

    /**
     * @SWG\Put(
     *   path="/relations/{relation_id}",
     *   tags={"relation"},
     *   summary="Update a relation",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Relation")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Update relation",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function update(UpdateRelationRequest $request, $publicId)
    {
        if ($request->action) {
            return $this->handleAction($request);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $client = $this->clientRepo->save($data, $request->entity());

        $client->load(['contacts']);

        return $this->itemResponse($client);
    }


    /**
     * @SWG\Delete(
     *   path="/relations/{relation_id}",
     *   tags={"relation"},
     *   summary="Delete a relation",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Relation")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Delete relation",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function destroy(UpdateRelationRequest $request)
    {
        $client = $request->entity();

        $this->clientRepo->delete($client);

        return $this->itemResponse($client);
    }

}
