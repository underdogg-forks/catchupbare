<?php namespace App\Http\Requests;

use Modules\Relations\Models\Relation;

class CreateInvoiceAPIRequest extends InvoiceRequest
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
            'email' => 'required_without:relation_id',
            'relation_id' => 'required_without:email',
            'invoice_items' => 'valid_invoice_items',
            'invoice_number' => 'unique:invoices,invoice_number,,id,company_id,' . $this->user()->company_id,
            'discount' => 'positive',
            //'invoice_date' => 'date',
            //'due_date' => 'date',
            //'start_date' => 'date',
            //'end_date' => 'date',
        ];

        if ($this->user()->company->client_number_counter) {
            $relationId = Relation::getPrivateId(request()->input('relation')['public_id']);
            $rules['relation.id_number'] = 'unique:relations,id_number,'.$relationId.',id,company_id,' . $this->user()->company_id;
        }

        return $rules;
    }
}
