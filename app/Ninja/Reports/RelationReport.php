<?php

namespace App\Ninja\Reports;

use Auth;
use Modules\Relations\Models\Relation;

class ClientReport extends AbstractReport
{
    public $columns = [
            'relation',
            'amount',
            'paid',
            'balance',
    ];

    public function run()
    {
        $company = Auth::user()->company;

        $relations = Relation::scope()
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function($query) {
                            $query->where('invoice_date', '>=', $this->startDate)
                                  ->where('invoice_date', '<=', $this->endDate)
                                  ->where('invoice_type_id', '=', INVOICE_TYPE_STANDARD)
                                  ->where('is_recurring', '=', false)
                                  ->withArchived();
                        }]);

        foreach ($relations->get() as $relation) {
            $amount = 0;
            $paid = 0;

            foreach ($relation->invoices as $invoice) {
                $amount += $invoice->amount;
                $paid += $invoice->getAmountPaid();
            }

            $this->data[] = [
                $this->isExport ? $relation->getDisplayName() : $relation->present()->link,
                $company->formatMoney($amount, $relation),
                $company->formatMoney($paid, $relation),
                $company->formatMoney($amount - $paid, $relation)
            ];

            $this->addToTotals($relation->currency_id, 'amount', $amount);
            $this->addToTotals($relation->currency_id, 'paid', $paid);
            $this->addToTotals($relation->currency_id, 'balance', $amount - $paid);
        }
    }
}
