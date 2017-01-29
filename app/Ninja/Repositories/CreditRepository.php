<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use App\Models\Credit;
use App\Models\Relation;

class CreditRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Credit';
    }

    public function find($relationPublicId = null, $filter = null)
    {
        $query = DB::table('credits')
                    ->join('companies', 'companies.id', '=', 'credits.company_id')
                    ->join('relations', 'relations.id', '=', 'credits.relation_id')
                    ->join('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->where('relations.company_id', '=', \Auth::user()->company_id)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)
                    ->select(
                        DB::raw('COALESCE(relations.currency_id, companies.currency_id) currency_id'),
                        DB::raw('COALESCE(relations.country_id, companies.country_id) country_id'),
                        'credits.public_id',
                        DB::raw("COALESCE(NULLIF(relations.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) relation_name"),
                        'relations.public_id as relation_public_id',
                        'relations.user_id as client_user_id',
                        'credits.amount',
                        'credits.balance',
                        'credits.credit_date',
                        'contacts.first_name',
                        'contacts.last_name',
                        'contacts.email',
                        'credits.private_notes',
                        'credits.deleted_at',
                        'credits.is_deleted',
                        'credits.user_id'
                    );

        if ($relationPublicId) {
            $query->where('relations.public_id', '=', $relationPublicId);
        } else {
            $query->whereNull('relations.deleted_at');
        }

        $this->applyFilters($query, ENTITY_CREDIT);

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('relations.name', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function getClientDatatable($relationId)
    {
        $query = DB::table('credits')
                    ->join('companies', 'companies.id', '=', 'credits.company_id')
                    ->join('relations', 'relations.id', '=', 'credits.relation_id')
                    ->where('credits.relation_id', '=', $relationId)
                    ->where('relations.deleted_at', '=', null)
                    ->where('credits.deleted_at', '=', null)
                    ->where('credits.balance', '>', 0)
                    ->select(
                        DB::raw('COALESCE(relations.currency_id, companies.currency_id) currency_id'),
                        DB::raw('COALESCE(relations.country_id, companies.country_id) country_id'),
                        'credits.amount',
                        'credits.balance',
                        'credits.credit_date'
                    );

        $table = \Datatable::query($query)
            ->addColumn('credit_date', function ($model) { return Utils::fromSqlDate($model->credit_date); })
            ->addColumn('amount', function ($model) { return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id); })
            ->addColumn('balance', function ($model) { return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id); })
            ->make();

        return $table;
    }

    public function save($input, $credit = null)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ($credit) {
            // do nothing
        } elseif ($publicId) {
            $credit = Credit::scope($publicId)->firstOrFail();
            \Log::warning('Entity not set in credit repo save');
        } else {
            $credit = Credit::createNew();
            $credit->relation_id = Relation::getPrivateId($input['relation']);
        }

        $credit->credit_date = Utils::toSqlDate($input['credit_date']);
        $credit->amount = Utils::parseFloat($input['amount']);
        $credit->balance = Utils::parseFloat($input['amount']);
        $credit->private_notes = trim($input['private_notes']);
        $credit->save();

        return $credit;
    }
}
