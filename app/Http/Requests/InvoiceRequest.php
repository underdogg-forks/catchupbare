<?php namespace App\Http\Requests;

use App\Models\Invoice;
use Input;
use Utils;
use App\Libraries\HistoryUtils;


class InvoiceRequest extends EntityRequest {

    protected $entityType = ENTITY_INVOICE;

    public function entity()
    {
        //$invoice = parent::entity();
        $the_invoice_id = Input::get('id');
        $invoice = Invoice::scope($the_invoice_id)->withTrashed()->firstOrFail();

        // support loading an invoice by its invoice number
        if ($this->invoice_number && ! $invoice) {
            $invoice = Invoice::scope()
                        ->whereInvoiceNumber($this->invoice_number)
                        ->withTrashed()
                        ->firstOrFail();
        }

        // eager load the invoice items
        if ($invoice && ! $invoice->relationLoaded('invoice_items')) {
            $invoice->load('invoice_items');
        }

        return $invoice;
    }

}
