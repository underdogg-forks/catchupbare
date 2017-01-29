<?php
namespace Modules\Relations\Repositories;

use DB;
use Cache;
use Auth;
use Modules\Relations\Models\Relation;
use App\Models\Contact;
use App\Events\ClientWasCreated;
use App\Events\ClientWasUpdated;
use App\Ninja\Repositories\BaseRepository;

class RelationRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'Modules\Relations\Models\Relation';
    }

    public function all()
    {
        return Relation::scope()
                ->with('user', 'contacts', 'country')
                ->withTrashed()
                ->where('is_deleted', '=', false)
                ->get();
    }

    public function find($filter = null, $userId = false)
    {
        $query = DB::table('relations')
                    ->join('companies', 'companies.id', '=', 'relations.company_id')
                    ->join('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->where('relations.company_id', '=', \Auth::user()->company_id)
                    ->where('contacts.is_primary', '=', true)
                    ->where('contacts.deleted_at', '=', null)
                    //->whereRaw('(relations.name != "" or contacts.first_name != "" or contacts.last_name != "" or contacts.email != "")') // filter out buy now invoices
                    ->select(
                        DB::raw('COALESCE(relations.currency_id, companies.currency_id) currency_id'),
                        DB::raw('COALESCE(relations.country_id, companies.country_id) country_id'),
                        DB::raw("CONCAT(contacts.first_name, ' ', contacts.last_name) contact"),
                        'relations.id',
                        'relations.name',
                        'contacts.first_name',
                        'contacts.last_name',
                        'relations.balance',
                        'relations.last_login',
                        'relations.created_at',
                        'relations.created_at as client_created_at',
                        'relations.work_phone',
                        'contacts.email',
                        'relations.deleted_at',
                        'relations.is_deleted',
                        'relations.user_id'
                    );

        $this->applyFilters($query, ENTITY_RELATION);

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('relations.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
        }

        if ($userId) {
            $query->where('relations.user_id', '=', $userId);
        }

        return $query;
    }

    public function save($data, $relation = null)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ($relation) {
           // do nothing
        } elseif (!$publicId || $publicId == '-1') {
            $relation = Relation::createNew();
            if (Auth::check() && Auth::user()->company->client_number_counter && empty($data['id_number'])) {
                $data['id_number'] = Auth::user()->company->getNextNumber();
            }
        } else {
            $relation = Relation::scope($publicId)->with('contacts')->firstOrFail();
        }

        if ($relation->is_deleted) {
            return $relation;
        }

        // convert currency code to id
        if (isset($data['currency_code'])) {
            $currencyCode = strtolower($data['currency_code']);
            $currency = Cache::get('currencies')->filter(function($item) use ($currencyCode) {
                return strtolower($item->code) == $currencyCode;
            })->first();
            if ($currency) {
                $data['currency_id'] = $currency->id;
            }
        }

        $relation->fill($data);
        $relation->save();

        /*
        if ( ! isset($data['contact']) && ! isset($data['contacts'])) {
            return $relation;
        }
        */

        $first = true;
        $contacts = isset($data['contact']) ? [$data['contact']] : $data['contacts'];
        $contactIds = [];

        // If the primary is set ensure it's listed first
        usort($contacts, function ($left, $right) {
            if (isset($right['is_primary']) && isset($left['is_primary'])) {
                return $right['is_primary'] - $left['is_primary'];
            } else {
                return 0;
            }
        });

        foreach ($contacts as $contact) {
            $contact = $relation->addContact($contact, $first);
            $contactIds[] = $contact->public_id;
            $first = false;
        }

        if ( ! $relation->wasRecentlyCreated) {
            foreach ($relation->contacts as $contact) {
                if (!in_array($contact->public_id, $contactIds)) {
                    $contact->delete();
                }
            }
        }

        if (!$publicId || $publicId == '-1') {
            event(new ClientWasCreated($relation));
        } else {
            event(new ClientWasUpdated($relation));
        }

        return $relation;
    }

    public function findPhonetically($relationName)
    {
        $relationNameMeta = metaphone($relationName);

        $map = [];
        $max = SIMILAR_MIN_THRESHOLD;
        $relationId = 0;

        $relations = Relation::scope()->get(['id', 'name', 'public_id']);

        foreach ($relations as $relation) {
            $map[$relation->id] = $relation;

            if ( ! $relation->name) {
                continue;
            }

            $similar = similar_text($relationNameMeta, metaphone($relation->name), $percent);

            if ($percent > $max) {
                $relationId = $relation->id;
                $max = $percent;
            }
        }

        $contacts = Contact::scope()->get(['relation_id', 'first_name', 'last_name', 'public_id']);

        foreach ($contacts as $contact) {
            if ( ! $contact->getFullName() || ! isset($map[$contact->relation_id])) {
                continue;
            }

            $similar = similar_text($relationNameMeta, metaphone($contact->getFullName()), $percent);

            if ($percent > $max) {
                $relationId = $contact->relation_id;
                $max = $percent;
            }
        }

        return ($relationId && isset($map[$relationId])) ? $map[$relationId] : null;
    }

}
