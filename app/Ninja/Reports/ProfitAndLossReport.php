<?php

namespace App\Ninja\Reports;

use Auth;
use App\Models\Payment;
use App\Models\Expense;

class ProfitAndLossReport extends AbstractReport
{
    public $columns = [
        'type',
        'relation',
        'amount',
        'date',
        'notes',
    ];

    public function run()
    {
        $company = Auth::user()->company;

        $payments = Payment::scope()
                        ->with('relation.contacts')
                        ->withArchived()
                        ->excludeFailed();

        foreach ($payments->get() as $payment) {
            $relation = $payment->relation;
            $this->data[] = [
                trans('texts.payment'),
                $relation ? ($this->isExport ? $relation->getDisplayName() : $relation->present()->link) : '',
                $company->formatMoney($payment->getCompletedAmount(), $relation),
                $payment->present()->payment_date,
                $payment->present()->method,
            ];

            $this->addToTotals($relation->currency_id, 'revenue', $payment->getCompletedAmount(), $payment->present()->month);
            $this->addToTotals($relation->currency_id, 'expenses', 0, $payment->present()->month);
            $this->addToTotals($relation->currency_id, 'profit', $payment->getCompletedAmount(), $payment->present()->month);
        }


        $expenses = Expense::scope()
                        ->with('relation.contacts')
                        ->withArchived();

        foreach ($expenses->get() as $expense) {
            $relation = $expense->relation;
            $this->data[] = [
                trans('texts.expense'),
                $relation ? ($this->isExport ? $relation->getDisplayName() : $relation->present()->link) : '',
                $expense->present()->amount,
                $expense->present()->expense_date,
                $expense->present()->category,
            ];

            $this->addToTotals($relation->currency_id, 'revenue', 0, $expense->present()->month);
            $this->addToTotals($relation->currency_id, 'expenses', $expense->amount, $expense->present()->month);
            $this->addToTotals($relation->currency_id, 'profit', $expense->amount * -1, $expense->present()->month);
        }


        //$this->addToTotals($relation->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
        //$this->addToTotals($relation->currency_id, 'amount', $invoice->amount);
        //$this->addToTotals($relation->currency_id, 'balance', $invoice->balance);
    }
}
