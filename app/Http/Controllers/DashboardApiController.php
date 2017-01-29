<?php namespace App\Http\Controllers;

use Auth;
use DB;
use App\Ninja\Repositories\DashboardRepository;
use App\Ninja\Transformers\ActivityTransformer;

class DashboardApiController extends BaseAPIController
{
    public function __construct(DashboardRepository $dashboardRepo)
    {
        parent::__construct();

        $this->dashboardRepo = $dashboardRepo;
    }

    public function index()
    {
        $user = Auth::user();
        $viewAll = $user->hasPermission('view_all');
        $userId = $user->id;
        $companyId = $user->company->id;

        $dashboardRepo = $this->dashboardRepo;
        $metrics = $dashboardRepo->totals($companyId, $userId, $viewAll);
        $paidToDate = $dashboardRepo->paidToDate($user->company, $userId, $viewAll);
        $averageInvoice = $dashboardRepo->averages($user->company, $userId, $viewAll);
        $balances = $dashboardRepo->balances($companyId, $userId, $viewAll);
        $activities = $dashboardRepo->activities($companyId, $userId, $viewAll);
        $pastDue = $dashboardRepo->pastDue($companyId, $userId, $viewAll);
        $upcoming = $dashboardRepo->upcoming($companyId, $userId, $viewAll);
        $payments = $dashboardRepo->payments($companyId, $userId, $viewAll);

        $hasQuotes = false;
        foreach ([$upcoming, $pastDue] as $data) {
            foreach ($data as $invoice) {
                if ($invoice->invoice_type_id == INVOICE_TYPE_QUOTE) {
                    $hasQuotes = true;
                }
            }
        }

        $data = [
            'id' => 1,
            'paidToDate' => count($paidToDate) && $paidToDate[0]->value ? $paidToDate[0]->value : 0,
            'paidToDateCurrency' => count($paidToDate) && $paidToDate[0]->currency_id ? $paidToDate[0]->currency_id : 0,
            'balances' => count($balances) && $balances[0]->value ? $balances[0]->value : 0,
            'balancesCurrency' => count($balances) && $balances[0]->currency_id ? $balances[0]->currency_id : 0,
            'averageInvoice' => count($averageInvoice) && $averageInvoice[0]->invoice_avg ? $averageInvoice[0]->invoice_avg : 0,
            'averageInvoiceCurrency' => count($averageInvoice) && $averageInvoice[0]->currency_id ? $averageInvoice[0]->currency_id : 0,
            'invoicesSent' => $metrics ? $metrics->invoices_sent : 0,
            'activeClients' => $metrics ? $metrics->active_clients : 0,
            'activities' => $this->createCollection($activities, new ActivityTransformer(), ENTITY_ACTIVITY),
        ];

        return $this->response($data);
    }
}
