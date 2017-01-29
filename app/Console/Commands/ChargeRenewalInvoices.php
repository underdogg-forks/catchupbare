<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\CompanyRepository;
use App\Services\PaymentService;
use App\Models\Invoice;
use App\Models\Company;

/**
 * Class ChargeRenewalInvoices
 */
class ChargeRenewalInvoices extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:charge-renewals';

    /**
     * @var string
     */
    protected $description = 'Charge renewal invoices';

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var CompanyRepository
     */
    protected $companyRepo;

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * ChargeRenewalInvoices constructor.
     * @param Mailer $mailer
     * @param CompanyRepository $repo
     * @param PaymentService $paymentService
     */
    public function __construct(Mailer $mailer, CompanyRepository $repo, PaymentService $paymentService)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->companyRepo = $repo;
        $this->paymentService = $paymentService;
    }

    public function fire()
    {
        $this->info(date('Y-m-d').' ChargeRenewalInvoices...');

        $ninjaAccount = $this->companyRepo->getNinjaAccount();
        $invoices = Invoice::whereCompanyId($ninjaAccount->id)
                        ->whereDueDate(date('Y-m-d'))
                        ->where('balance', '>', 0)
                        ->with('client')
                        ->orderBy('id')
                        ->get();

        $this->info(count($invoices).' invoices found');

        foreach ($invoices as $invoice) {

            // check if company has switched to free since the invoice was created
            $company = Company::find($invoice->client->public_id);

            if ( ! $company) {
                continue;
            }

            $corporation = $company->corporation;
            if ( ! $corporation->plan || $corporation->plan == PLAN_FREE) {
                continue;
            }

            $this->info("Charging invoice {$invoice->invoice_number}");
            if ( ! $this->paymentService->autoBillInvoice($invoice)) {
                $this->info('Failed to auto-bill, emailing invoice');
                $this->mailer->sendInvoice($invoice);
            }
        }

        $this->info('Done');

        if ($errorEmail = env('ERROR_EMAIL')) {
            \Mail::raw('EOM', function ($message) use ($errorEmail) {
                $message->to($errorEmail)
                        ->from(CONTACT_EMAIL)
                        ->subject('ChargeRenewalInvoices: Finished successfully');
            });
        }
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
