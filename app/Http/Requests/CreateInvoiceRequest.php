<?php namespace App\Http\Requests;

use Modules\Relations\Models\Relation;

class CreateInvoiceRequest extends InvoiceRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create', ENTITY_INVOICE);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'relation.contacts' => 'valid_contacts',
            'invoice_items' => 'valid_invoice_items',
            'invoice_number' => 'required|unique:invoices,invoice_number,,id,company_id,' . $this->user()->company_id,
            'discount' => 'positive',
            'invoice_date' => 'required',
            //'due_date' => 'date',
            //'start_date' => 'date',
            //'end_date' => 'date',
        ];

        /*if ($this->user()->company->client_number_counter) {
            $relationId = Relation::getPrivateId(request()->input('relation')['id']);
            $rules['relation.id_number'] = 'unique:relations,id_number,'.$relationId.',id,company_id,' . $this->user()->company_id;
        }*/

        /* There's a problem parsing the dates
        if (Request::get('is_recurring') && Request::get('start_date') && Request::get('end_date')) {
            $rules['end_date'] = 'after' . Request::get('start_date');
        }
        */

        return $rules;
    }
}
