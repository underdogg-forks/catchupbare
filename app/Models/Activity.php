<?php namespace App\Models;

use Auth;
use Eloquent;

/**
 * Class Activity
 */
class Activity extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @param $query
     * @return mixed
     */
    public function scopeScope($query)
    {
        return $query->whereCompanyId(Auth::user()->company_id);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo('App\Models\Company');
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function contact()
    {
        return $this->belongsTo('App\Models\Contact')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function relation()
    {
        return $this->belongsTo('App\Models\Relation')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function credit()
    {
        return $this->belongsTo('App\Models\Credit')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function payment()
    {
        return $this->belongsTo('App\Models\Payment')->withTrashed();
    }

    public function task()
    {
        return $this->belongsTo('App\Models\Task')->withTrashed();
    }

    public function expense()
    {
        return $this->belongsTo('App\Models\Expense')->withTrashed();
    }

    public function key()
    {
        return sprintf('%s-%s-%s', $this->activity_type_id, $this->relation_id, $this->created_at->timestamp);
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        $activityTypeId = $this->activity_type_id;
        $company = $this->company;
        $relation = $this->relation;
        $user = $this->user;
        $invoice = $this->invoice;
        $contactId = $this->contact_id;
        $payment = $this->payment;
        $credit = $this->credit;
        $expense = $this->expense;
        $isSystem = $this->is_system;
        $task = $this->task;

        $data = [
            'relation' => $relation ? link_to($relation->getRoute(), $relation->getDisplayName()) : null,
            'user' => $isSystem ? '<i>' . trans('texts.system') . '</i>' : $user->getDisplayName(),
            'invoice' => $invoice ? link_to($invoice->getRoute(), $invoice->getDisplayName()) : null,
            'quote' => $invoice ? link_to($invoice->getRoute(), $invoice->getDisplayName()) : null,
            'contact' => $contactId ? $relation->getDisplayName() : $user->getDisplayName(),
            'payment' => $payment ? $payment->transaction_reference : null,
            'payment_amount' => $payment ? $company->formatMoney($payment->amount, $payment) : null,
            'adjustment' => $this->adjustment ? $company->formatMoney($this->adjustment, $this) : null,
            'credit' => $credit ? $company->formatMoney($credit->amount, $relation) : null,
            'task' => $task ? link_to($task->getRoute(), substr($task->description, 0, 30).'...') : null,
            'expense' => $expense ? link_to($expense->getRoute(), substr($expense->public_notes, 0, 30).'...') : null,
        ];

        return trans("texts.activity_{$activityTypeId}", $data);
    }
}
