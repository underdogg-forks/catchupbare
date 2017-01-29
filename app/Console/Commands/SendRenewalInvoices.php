<?php namespace App\Console\Commands;

use Utils;
use Illuminate\Console\Command;
use App\Models\Corporation;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\CompanyRepository;

/**
 * Class SendRenewalInvoices
 */
class SendRenewalInvoices extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:send-renewals';

    /**
     * @var string
     */
    protected $description = 'Send renewal invoices';

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var CompanyRepository
     */
    protected $companyRepo;

    /**
     * SendRenewalInvoices constructor.
     *
     * @param Mailer $mailer
     * @param CompanyRepository $repo
     */
    public function __construct(Mailer $mailer, CompanyRepository $repo)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->companyRepo = $repo;
    }

    public function fire()
    {
        $this->info(date('Y-m-d').' Running SendRenewalInvoices...');

        // get all companies with plans expiring in 10 days
        $corporations = Corporation::whereRaw("datediff(plan_expires, curdate()) = 10 and (plan = 'pro' or plan = 'enterprise')")
                        ->orderBy('id')
                        ->get();
        $this->info(count($corporations).' corporations found renewing in 10 days');

        foreach ($corporations as $corporation) {
            if (!count($corporation->companies)) {
                continue;
            }

            $company = $corporation->companies->sortBy('id')->first();
            $plan = [];
            $plan['plan'] = $corporation->plan;
            $plan['term'] = $corporation->plan_term;
            $plan['num_users'] = $corporation->num_users;
            $plan['price'] = min($corporation->plan_price, Utils::getPlanPrice($plan));

            if ($corporation->pending_plan) {
                $plan['plan'] = $corporation->pending_plan;
                $plan['term'] = $corporation->pending_term;
                $plan['num_users'] = $corporation->pending_num_users;
                $plan['price'] = min($corporation->pending_plan_price, Utils::getPlanPrice($plan));
            }

            if ($plan['plan'] == PLAN_FREE || !$plan['plan'] || !$plan['term'] || !$plan['price']){
                continue;
            }

            $relation = $this->companyRepo->getNinjaClient($company);
            $invitation = $this->companyRepo->createNinjaInvoice($relation, $company, $plan, 0, false);

            // set the due date to 10 days from now
            $invoice = $invitation->invoice;
            $invoice->due_date = date('Y-m-d', strtotime('+ 10 days'));
            $invoice->save();

            $term = $plan['term'];
            $plan = $plan['plan'];

            if ($term == PLAN_TERM_YEARLY) {
                $this->mailer->sendInvoice($invoice);
                $this->info("Sent {$term}ly {$plan} invoice to {$relation->getDisplayName()}");
            } else {
                $this->info("Created {$term}ly {$plan} invoice for {$relation->getDisplayName()}");
            }
        }

        $this->info('Done');

        if ($errorEmail = env('ERROR_EMAIL')) {
            \Mail::raw('EOM', function ($message) use ($errorEmail) {
                $message->to($errorEmail)
                        ->from(CONTACT_EMAIL)
                        ->subject('SendRenewalInvoices: Finished successfully');
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
