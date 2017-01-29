<?php

namespace App\Ninja\Reports;

use Auth;
use Modules\Relations\Models\Relation;

class AgingReport extends AbstractReport
{
    public $columns = [
        'relation',
        'invoice_number',
        'invoice_date',
        'due_date',
        'age' => ['group-number-30'],
        'amount',
        'balance',
    ];

    public function run()
    {
        $company = Auth::user()->company;

        $relations = Relation::scope()
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function($query) {
                            $query->invoices()
                                  ->whereIsPublic(true)
                                  ->withArchived()
                                  ->where('balance', '>', 0)
                                  ->where('invoice_date', '>=', $this->startDate)
                                  ->where('invoice_date', '<=', $this->endDate)
                                  ->with(['invoice_items']);
                        }]);

        foreach ($relations->get() as $relation) {
            foreach ($relation->invoices as $invoice) {

                $this->data[] = [
                    $this->isExport ? $relation->getDisplayName() : $relation->present()->link,
                    $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                    $invoice->present()->invoice_date,
                    $invoice->present()->due_date,
                    $invoice->present()->age,
                    $company->formatMoney($invoice->amount, $relation),
                    $company->formatMoney($invoice->balance, $relation),
                ];

                $this->addToTotals($relation->currency_id, $invoice->present()->ageGroup, $invoice->balance);

                //$this->addToTotals($relation->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
                //$this->addToTotals($relation->currency_id, 'amount', $invoice->amount);
                //$this->addToTotals($relation->currency_id, 'balance', $invoice->balance);
            }
        }
    }
}
