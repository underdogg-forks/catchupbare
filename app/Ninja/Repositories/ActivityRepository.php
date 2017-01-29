<?php namespace App\Ninja\Repositories;

use DB;
use Auth;
use Utils;
use Request;
use App\Models\Activity;
use App\Models\Relation;
use App\Models\Invitation;

class ActivityRepository
{
    public function create($entity, $activityTypeId, $balanceChange = 0, $paidToDateChange = 0, $altEntity = null, $notes = false)
    {
        if ($entity instanceof Relation) {
            $client = $entity;
        } elseif ($entity instanceof Invitation) {
            $client = $entity->invoice->relation;
        } else {
            $client = $entity->relation;
        }

        // init activity and copy over context
        $activity = self::getBlank($altEntity ?: ($client ?: $entity));
        $activity = Utils::copyContext($activity, $entity);
        $activity = Utils::copyContext($activity, $altEntity);

        $activity->activity_type_id = $activityTypeId;
        $activity->adjustment = $balanceChange;
        $activity->relation_id = $client ? $client->id : 0;
        $activity->balance = $client ? ($client->balance + $balanceChange) : 0;
        $activity->notes = $notes ?: '';

        $keyField = $entity->getKeyField();
        $activity->$keyField = $entity->id;

        $activity->ip = Request::getClientIp();
        $activity->save();

        if ($client) {
            $client->updateBalances($balanceChange, $paidToDateChange);
        }

        return $activity;
    }

    private function getBlank($entity)
    {
        $activity = new Activity();

        if (Auth::check() && Auth::user()->company_id == $entity->company_id) {
            $activity->user_id = Auth::user()->id;
            $activity->company_id = Auth::user()->company_id;
        } else {
            $activity->user_id = $entity->user_id;
            $activity->company_id = $entity->company_id;
            $activity->is_system = true;
        }

        $activity->token_id = session('token_id');

        return $activity;
    }

    public function findByClientId($relationId)
    {
        return DB::table('activities')
                    ->join('companies', 'companies.id', '=', 'activities.company_id')
                    ->join('users', 'users.id', '=', 'activities.user_id')
                    ->join('relations', 'relations.id', '=', 'activities.relation_id')
                    ->leftJoin('contacts', 'contacts.relation_id', '=', 'relations.id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'activities.invoice_id')
                    ->leftJoin('payments', 'payments.id', '=', 'activities.payment_id')
                    ->leftJoin('credits', 'credits.id', '=', 'activities.credit_id')
                    ->leftJoin('tasks', 'tasks.id', '=', 'activities.task_id')
                    ->leftJoin('expenses', 'expenses.id', '=', 'activities.expense_id')
                    ->where('relations.id', '=', $relationId)
                    ->where('contacts.is_primary', '=', 1)
                    ->whereNull('contacts.deleted_at')
                    ->select(
                        DB::raw('COALESCE(relations.currency_id, companies.currency_id) currency_id'),
                        DB::raw('COALESCE(relations.country_id, companies.country_id) country_id'),
                        'activities.id',
                        'activities.created_at',
                        'activities.contact_id',
                        'activities.activity_type_id',
                        'activities.is_system',
                        'activities.balance',
                        'activities.adjustment',
                        'activities.notes',
                        'users.first_name as user_first_name',
                        'users.last_name as user_last_name',
                        'users.email as user_email',
                        'invoices.invoice_number as invoice',
                        'invoices.public_id as invoice_public_id',
                        'invoices.is_recurring',
                        'relations.name as relation_name',
                        'companies.name as acc_name',
                        'relations.public_id as relation_public_id',
                        'contacts.id as contact',
                        'contacts.first_name as first_name',
                        'contacts.last_name as last_name',
                        'contacts.email as email',
                        'payments.transaction_reference as payment',
                        'payments.amount as payment_amount',
                        'credits.amount as credit',
                        'tasks.description as task_description',
                        'tasks.public_id as task_public_id',
                        'expenses.public_notes as expense_public_notes',
                        'expenses.public_id as expense_public_id'
                    );
    }

}
