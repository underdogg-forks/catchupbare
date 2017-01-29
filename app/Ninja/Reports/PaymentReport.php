<?php

namespace App\Ninja\Reports;

use Auth;
use App\Models\Payment;

class PaymentReport extends AbstractReport
{
    public $columns = [
        'relation',
        'invoice_number',
        'invoice_date',
        'amount',
        'payment_date',
        'paid',
        'method',
    ];

    public function run()
    {
        $company = Auth::user()->company;

        $payments = Payment::scope()
                        ->withArchived()
                        ->excludeFailed()
                        ->whereHas('relation', function($query) {
                            $query->where('is_deleted', '=', false);
                        })
                        ->whereHas('invoice', function($query) {
                            $query->where('is_deleted', '=', false);
                        })
                        ->with('relation.contacts', 'invoice', 'payment_type', 'acc_gateway.gateway')
                        ->where('payment_date', '>=', $this->startDate)
                        ->where('payment_date', '<=', $this->endDate);

        foreach ($payments->get() as $payment) {
            $invoice = $payment->invoice;
            $client = $payment->relation;
            $this->data[] = [
                $this->isExport ? $client->getDisplayName() : $client->present()->link,
                $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                $invoice->present()->invoice_date,
                $company->formatMoney($invoice->amount, $client),
                $payment->present()->payment_date,
                $company->formatMoney($payment->getCompletedAmount(), $client),
                $payment->present()->method,
            ];

            $this->addToTotals($client->currency_id, 'amount', $invoice->amount);
            $this->addToTotals($client->currency_id, 'paid', $payment->getCompletedAmount());
        }
    }
}
