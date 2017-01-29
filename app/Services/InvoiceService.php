<?php namespace App\Services;

use App\Models\Invoice;
use Auth;
use Utils;
use App\Ninja\Repositories\InvoiceRepository;
use Modules\Relations\Repositories\RelationRepository;
use App\Events\QuoteInvitationWasApproved;
use App\Models\Invitation;
use Modules\Relations\Models\Relation;
use App\Ninja\Datatables\InvoiceDatatable;

class InvoiceService extends BaseService
{
    /**
     * @var RelationRepository
     */
    protected $relationRepo;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * InvoiceService constructor.
     *
     * @param RelationRepository $relationRepo
     * @param InvoiceRepository $invoiceRepo
     * @param DatatableService $datatableService
     */
    public function __construct(
        RelationRepository $relationRepo,
        InvoiceRepository $invoiceRepo,
        DatatableService $datatableService
    )
    {
        $this->relationRepo = $relationRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return InvoiceRepository
     */
    protected function getRepo()
    {
        return $this->invoiceRepo;
    }

    /**
     * @param array $data
     * @param Invoice|null $invoice
     * @return \App\Models\Invoice|Invoice|mixed
     */
    public function save(array $data, Invoice $invoice = null)
    {
        if (isset($data['relation'])) {
            $canSaveRelation = false;
            $canViewRelation = false;
            $relationPublicId = array_get($data, 'relation.id') ?: array_get($data, 'relation.id');
            if (empty($relationPublicId) || $relationPublicId == '-1') {
                $canSaveRelation = Auth::user()->can('create', ENTITY_RELATION);
            } else {
                $relation = Relation::scope($relationPublicId)->first();
                $canSaveRelation = Auth::user()->can('edit', $relation);
                $canViewRelation = Auth::user()->can('view', $relation);
            }
            if ($canSaveRelation) {
                $relation = $this->relationRepo->save($data['relation']);
            }
            if ($canSaveRelation || $canViewRelation) {
                $data['relation_id'] = $relation->id;
            }
        }

        $invoice = $this->invoiceRepo->save($data, $invoice);

        $relation = $invoice->relation;
        $relation->load('contacts');
        $sendInvoiceIds = [];

        foreach ($relation->contacts as $contact) {
            if ($contact->send_invoice) {
                $sendInvoiceIds[] = $contact->id;
            }
        }

        // if no contacts are selected auto-select the first to ensure there's an invitation
        if ( ! count($sendInvoiceIds)) {
            $sendInvoiceIds[] = $relation->contacts[0]->id;
        }

        foreach ($relation->contacts as $contact) {
            $invitation = Invitation::scope()->whereContactId($contact->id)->whereInvoiceId($invoice->id)->first();

            if (in_array($contact->id, $sendInvoiceIds) && !$invitation) {
                $invitation = Invitation::createNew();
                $invitation->invoice_id = $invoice->id;
                $invitation->contact_id = $contact->id;
                $invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
                $invitation->save();
            } elseif (!in_array($contact->id, $sendInvoiceIds) && $invitation) {
                $invitation->delete();
            }
        }

        if ($invoice->is_public && ! $invoice->areInvitationsSent()) {
            $invoice->markInvitationsSent();
        }

        return $invoice;
    }

    /**
     * @param $quote
     * @param Invitation|null $invitation
     * @return mixed
     */
    public function convertQuote($quote)
    {
        return $this->invoiceRepo->cloneInvoice($quote, $quote->id);
    }

    /**
     * @param $quote
     * @param Invitation|null $invitation
     * @return mixed|null
     */
    public function approveQuote($quote, Invitation $invitation = null)
    {
        $company = $quote->company;

        if ( ! $company->hasFeature(FEATURE_QUOTES) || ! $quote->isType(INVOICE_TYPE_QUOTE) || $quote->quote_invoice_id) {
            return null;
        }

        if ($company->auto_convert_quote) {
            $invoice = $this->convertQuote($quote);

            foreach ($invoice->invitations as $invoiceInvitation) {
                if ($invitation->contact_id == $invoiceInvitation->contact_id) {
                    $invitation = $invoiceInvitation;
                }
            }
        } else {
            $quote->markApproved();
        }

        event(new QuoteInvitationWasApproved($quote, $invitation));

        return $invitation->invitation_key;
    }

    public function getDatatable($companyId, $relationPublicId = null, $entityType, $search)
    {
        $datatable = new InvoiceDatatable(true, $relationPublicId);
        $datatable->entityType = $entityType;

        $query = $this->invoiceRepo->getInvoices($companyId, $relationPublicId, $entityType, $search)
                    ->where('invoices.invoice_type_id', '=', $entityType == ENTITY_QUOTE ? INVOICE_TYPE_QUOTE : INVOICE_TYPE_STANDARD);

        if(!Utils::hasPermission('view_all')){
            $query->where('invoices.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }

}
