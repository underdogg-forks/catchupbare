<?php namespace App\Http\Requests;

class UpdateExpenseCategoryRequest extends ExpenseCategoryRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('edit', $this->entity());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
          return [
            'name' => 'required',
            'name' => sprintf('required|unique:expense_categories,name,%s,id,company_id,%s', $this->entity()->id, $this->user()->company_id),
        ];
    }
}
