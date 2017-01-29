<?php

namespace App\Ninja\Reports;

use Auth;
use App\Models\Relation;

class InvoiceReport extends AbstractReport
{
    public $columns = [
        'relation',
        'invoice_number',
        'invoice_date',
        'amount',
        'payment_date',
        'paid',
        'method'
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
                                  ->with(['payments' => function($query) {
                                        $query->withArchived()
                                              ->excludeFailed()
                                              ->with('payment_type', 'acc_gateway.gateway');
                                  }, 'invoice_items']);
                        }]);

        foreach ($relations->get() as $relation) {
            foreach ($relation->invoices as $invoice) {

                $payments = count($invoice->payments) ? $invoice->payments : [false];
                foreach ($payments as $payment) {
                    if ( ! $payment && $status == 'paid') {
                        continue;
                    } elseif ($payment && $status == 'unpaid') {
                        continue;
                    }
                    $this->data[] = [
                        $this->isExport ? $relation->getDisplayName() : $relation->present()->link,
                        $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                        $invoice->present()->invoice_date,
                        $company->formatMoney($invoice->amount, $relation),
                        $payment ? $payment->present()->payment_date : '',
                        $payment ? $company->formatMoney($payment->getCompletedAmount(), $relation) : '',
                        $payment ? $payment->present()->method : '',
                    ];

                    $this->addToTotals($relation->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
                }

                $this->addToTotals($relation->currency_id, 'amount', $invoice->amount);
                $this->addToTotals($relation->currency_id, 'balance', $invoice->balance);
            }
        }
    }
}
