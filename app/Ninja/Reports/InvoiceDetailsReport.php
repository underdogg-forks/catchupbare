<?php

namespace App\Ninja\Reports;

use Auth;
use Modules\Relations\Models\Relation;

class InvoiceDetailsReport extends AbstractReport
{
    public $columns = [
        'relation',
        'invoice_number',
        'invoice_date',
        'product',
        'qty',
        'cost',
        //'tax_rate1',
        //'tax_rate2',
    ];

    public function run()
    {
        $company = Auth::user()->company;
        $status = $this->options['invoice_status'];

        $relations = Relation::scope()
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function($query) use ($status) {
                            if ($status == 'draft') {
                                $query->whereIsPublic(false);
                            } elseif ($status == 'unpaid' || $status == 'paid') {
                                $query->whereIsPublic(true);
                            }
                            $query->invoices()
                                  ->withArchived()
                                  ->where('invoice_date', '>=', $this->startDate)
                                  ->where('invoice_date', '<=', $this->endDate)
                                  ->with(['invoice_items']);
                        }]);

        foreach ($relations->get() as $relation) {
            foreach ($relation->invoices as $invoice) {
                foreach ($invoice->invoice_items as $item) {
                    $this->data[] = [
                        $this->isExport ? $relation->getDisplayName() : $relation->present()->link,
                        $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                        $invoice->present()->invoice_date,
                        $item->product_key,
                        $item->qty,
                        $company->formatMoney($item->cost, $relation),
                    ];
                }

                //$this->addToTotals($relation->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
                //$this->addToTotals($relation->currency_id, 'amount', $invoice->amount);
                //$this->addToTotals($relation->currency_id, 'balance', $invoice->balance);
            }
        }
    }
}
