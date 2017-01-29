<?php

namespace App\Ninja\Reports;

use Auth;
use App\Models\Client;

class ClientReport extends AbstractReport
{
    public $columns = [
            'client',
            'amount',
            'paid',
            'balance',
    ];

    public function run()
    {
        $company = Auth::user()->company;

        $clients = Client::scope()
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function($query) {
                            $query->where('invoice_date', '>=', $this->startDate)
                                  ->where('invoice_date', '<=', $this->endDate)
                                  ->where('invoice_type_id', '=', INVOICE_TYPE_STANDARD)
                                  ->where('is_recurring', '=', false)
                                  ->withArchived();
                        }]);

        foreach ($clients->get() as $client) {
            $amount = 0;
            $paid = 0;

            foreach ($client->invoices as $invoice) {
                $amount += $invoice->amount;
                $paid += $invoice->getAmountPaid();
            }

            $this->data[] = [
                $this->isExport ? $client->getDisplayName() : $client->present()->link,
                $company->formatMoney($amount, $client),
                $company->formatMoney($paid, $client),
                $company->formatMoney($amount - $paid, $client)
            ];

            $this->addToTotals($client->currency_id, 'amount', $amount);
            $this->addToTotals($client->currency_id, 'paid', $paid);
            $this->addToTotals($client->currency_id, 'balance', $amount - $paid);
        }
    }
}
