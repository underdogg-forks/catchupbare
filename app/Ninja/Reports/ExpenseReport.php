<?php

namespace App\Ninja\Reports;

use Auth;
use Utils;
use App\Models\Expense;

class ExpenseReport extends AbstractReport
{
    public $columns = [
        'vendor',
        'relation',
        'date',
        'category',
        'expense_amount',
    ];

    public function run()
    {
        $company = Auth::user()->company;

        $expenses = Expense::scope()
                        ->withArchived()
                        ->with('relation.contacts', 'vendor')
                        ->where('expense_date', '>=', $this->startDate)
                        ->where('expense_date', '<=', $this->endDate);

        foreach ($expenses->get() as $expense) {
            $amount = $expense->amountWithTax();

            $this->data[] = [
                $expense->vendor ? ($this->isExport ? $expense->vendor->name : $expense->vendor->present()->link) : '',
                $expense->relation ? ($this->isExport ? $expense->relation->getDisplayName() : $expense->relation->present()->link) : '',
                $expense->present()->expense_date,
                $expense->present()->category,
                Utils::formatMoney($amount, $expense->currency_id),
            ];

            $this->addToTotals($expense->expense_currency_id, 'amount', $amount);
            $this->addToTotals($expense->invoice_currency_id, 'amount', 0);
        }
    }
}
