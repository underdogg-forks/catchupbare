<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use Auth;
use App\Models\Project;

class ProjectRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Project';
    }

    public function all()
    {
        return Project::scope()->get();
    }

    public function find($filter = null, $userId = false)
    {
        $query = DB::table('projects')
                ->where('projects.company_id', '=', Auth::user()->company_id)
                ->leftjoin('relations', 'relations.id', '=', 'projects.relation_id')
                ->leftJoin('contacts', 'contacts.relation_id', '=', 'relations.id')
                ->where('contacts.deleted_at', '=', null)
                ->where('relations.deleted_at', '=', null)
                ->where(function ($query) { // handle when relation isn't set
                    $query->where('contacts.is_primary', '=', true)
                          ->orWhere('contacts.is_primary', '=', null);
                })
                ->select(
                    'projects.name as project',
                    'projects.public_id',
                    'projects.user_id',
                    'projects.deleted_at',
                    'projects.is_deleted',
                    DB::raw("COALESCE(NULLIF(relations.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) relation_name"),
                    'relations.user_id as client_user_id',
                    'relations.public_id as relation_public_id'
                );

        $this->applyFilters($query, ENTITY_PROJECT);

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('relations.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%')
                      ->orWhere('projects.name', 'like', '%'.$filter.'%');
            });
        }

        if ($userId) {
            $query->where('projects.user_id', '=', $userId);
        }

        return $query;
    }

    public function save($input, $project = false)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ( ! $project) {
            $project = Project::createNew();
            $project['relation_id'] = $input['relation_id'];
        }

        $project->fill($input);
        $project->save();

        return $project;
    }
}
