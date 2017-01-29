<?php

namespace App\Ninja\Reports;

use Auth;
use App\Models\Relation;

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

        foreach ($relations->get() as $client) {
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
