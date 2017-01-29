<?php namespace App\Ninja\Transformers;

use App\Models\Company;

/**
 * Class AccountTransformer
 */
class AccountTransformer extends EntityTransformer
{
    /**
     * @var array
     */
    protected $defaultIncludes = [
        'users',
        'products',
        'tax_rates',
        'expense_categories',
        'projects',
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'clients',
        'invoices',
        'payments',
    ];

    /**
     * @param Company $company
     * @return \League\Fractal\Resource\Collection
     */
    public function includeExpenseCategories(Company $company)
    {
        $transformer = new ExpenseCategoryTransformer($company, $this->serializer);
        return $this->includeCollection($company->expense_categories, $transformer, 'expense_categories');
    }

    /**
     * @param Company $company
     * @return \League\Fractal\Resource\Collection
     */
    public function includeProjects(Company $company)
    {
        $transformer = new ProjectTransformer($company, $this->serializer);
        return $this->includeCollection($company->projects, $transformer, 'projects');
    }

    /**
     * @param Company $company
     * @return \League\Fractal\Resource\Collection
     */
    public function includeUsers(Company $company)
    {
        $transformer = new UserTransformer($company, $this->serializer);
        return $this->includeCollection($company->users, $transformer, 'users');
    }

    /**
     * @param Company $company
     * @return \League\Fractal\Resource\Collection
     */
    public function includeClients(Company $company)
    {
        $transformer = new ClientTransformer($company, $this->serializer);
        return $this->includeCollection($company->clients, $transformer, 'clients');
    }

    /**
     * @param Company $company
     * @return \League\Fractal\Resource\Collection
     */
    public function includeInvoices(Company $company)
    {
        $transformer = new InvoiceTransformer($company, $this->serializer);
        return $this->includeCollection($company->invoices, $transformer, 'invoices');
    }

    /**
     * @param Company $company
     * @return \League\Fractal\Resource\Collection
     */
    public function includeProducts(Company $company)
    {
        $transformer = new ProductTransformer($company, $this->serializer);
        return $this->includeCollection($company->products, $transformer, 'products');
    }

    /**
     * @param Company $company
     * @return \League\Fractal\Resource\Collection
     */
    public function includeTaxRates(Company $company)
    {
        $transformer = new TaxRateTransformer($company, $this->serializer);
        return $this->includeCollection($company->tax_rates, $transformer, 'taxRates');
    }

    /**
     * @param Company $company
     * @return \League\Fractal\Resource\Collection
     */
    public function includePayments(Company $company)
    {
        $transformer = new PaymentTransformer($company, $this->serializer);
        return $this->includeCollection($company->payments, $transformer, 'payments');
    }

    /**
     * @param Company $company
     * @return array
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     */
    public function transform(Company $company)
    {
        return [
            'acc_key' => $company->acc_key,
            'name' => $company->present()->name,
            'id_number' => $company->id_number,
            'currency_id' => (int) $company->currency_id,
            'timezone_id' => (int) $company->timezone_id,
            'date_format_id' => (int) $company->date_format_id,
            'datetime_format_id' => (int) $company->datetime_format_id,
            'updated_at' => $this->getTimestamp($company->updated_at),
            'archived_at' => $this->getTimestamp($company->deleted_at),
            'address1' => $company->address1,
            'address2' => $company->address2,
            'city' => $company->city,
            'state' => $company->state,
            'postal_code' => $company->postal_code,
            'country_id' => (int) $company->country_id,
            'invoice_terms' => $company->invoice_terms,
            'email_footer' => $company->email_footer,
            'industry_id' => (int) $company->industry_id,
            'size_id' => (int) $company->size_id,
            'invoice_taxes' => (bool) $company->invoice_taxes,
            'invoice_item_taxes' => (bool) $company->invoice_item_taxes,
            'invoice_design_id' => (int) $company->invoice_design_id,
            'client_view_css' => (string) $company->client_view_css,
            'work_phone' => $company->work_phone,
            'work_email' => $company->work_email,
            'language_id' => (int) $company->language_id,
            'fill_products' => (bool) $company->fill_products,
            'update_products' => (bool) $company->update_products,
            'vat_number' => $company->vat_number,
            'custom_invoice_label1' => $company->custom_invoice_label1,
            'custom_invoice_label2' => $company->custom_invoice_label2,
            'custom_invoice_taxes1' => $company->custom_invoice_taxes1,
            'custom_invoice_taxes2' => $company->custom_invoice_taxes1,
            'custom_label1' => $company->custom_label1,
            'custom_label2' => $company->custom_label2,
            'custom_value1' => $company->custom_value1,
            'custom_value2' => $company->custom_value2,
            'logo' => $company->logo,
        ];
    }
}
