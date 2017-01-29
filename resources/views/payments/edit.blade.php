@extends('header')

@section('head')
    @parent

    @include('money_script')

    <style type="text/css">
        .input-group-addon {
            min-width: 40px;
        }
    </style>
@stop

@section('content')

	{!! Former::open($url)
        ->addClass('col-md-10 col-md-offset-1 warn-on-exit main-form')
        ->onsubmit('onFormSubmit(event)')
        ->method($method)
        ->rules(array(
    		'relation' => 'required',
    		'invoice' => 'required',
    		'amount' => 'required',
    	)) !!}

    @if ($payment)
        {!! Former::populate($payment) !!}
    @endif

    <span style="display:none">
        {!! Former::text('public_id') !!}
        {!! Former::text('action') !!}
    </span>

	<div class="row">
		<div class="col-md-10 col-md-offset-1">

            <div class="panel panel-default">
            <div class="panel-body">

            @if ($payment)
             {!! Former::plaintext()->label('relation')->value($payment->relation->getDisplayName()) !!}
             {!! Former::plaintext()->label('invoice')->value($payment->invoice->getDisplayName()) !!}
             {!! Former::plaintext()->label('amount')->value($payment->present()->amount) !!}
            @else
			 {!! Former::select('relation')->addOption('', '')->addGroupClass('relation-select') !!}
			 {!! Former::select('invoice')->addOption('', '')->addGroupClass('invoice-select') !!}
			 {!! Former::text('amount') !!}

             @if (isset($paymentTypeId) && $paymentTypeId)
               {!! Former::populateField('payment_type_id', $paymentTypeId) !!}
             @endif
            @endif

            @if (!$payment || !$payment->acc_gateway_id)
			 {!! Former::select('payment_type_id')
                    ->addOption('','')
                    ->fromQuery($paymentTypes, 'name', 'id')
                    ->addGroupClass('payment-type-select') !!}
            @endif

			{!! Former::text('payment_date')
                        ->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))
                        ->addGroupClass('payment_date')
                        ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
			{!! Former::text('transaction_reference') !!}

            @if (!$payment)
                {!! Former::checkbox('email_receipt')->label('&nbsp;')->text(trans('texts.email_receipt'))->value(1) !!}
            @endif

            </div>
            </div>

		</div>
	</div>


	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->appendIcon(Icon::create('remove-circle'))->asLinkTo(URL::to('/payments'))->large() !!}
        @if (!$payment || !$payment->is_deleted)
            {!! Button::success(trans('texts.save'))->withAttributes(['id' => 'saveButton'])->appendIcon(Icon::create('floppy-disk'))->submit()->large() !!}
        @endif

        @if ($payment)
            {!! DropdownButton::normal(trans('texts.more_actions'))
                  ->withContents($actions)
                  ->large()
                  ->dropup() !!}
        @endif

	</center>

	{!! Former::close() !!}

	<script type="text/javascript">

	var invoices = {!! $invoices !!};
	var relations = {!! $relations !!};

	$(function() {

        @if ($payment)
          $('#payment_date').datepicker('update', '{{ $payment->payment_date }}')
          @if ($payment->payment_type_id != PAYMENT_TYPE_CREDIT)
            $("#payment_type_id option[value='{{ PAYMENT_TYPE_CREDIT }}']").remove();
          @endif
        @else
          $('#payment_date').datepicker('update', new Date());
		  populateInvoiceComboboxes({{ $relationPublicId }}, {{ $invoicePublicId }});
        @endif

		$('#payment_type_id').combobox();

        @if (!$payment && !$relationPublicId)
            $('.relation-select input.form-control').focus();
        @elseif (!$payment && !$invoicePublicId)
            $('.invoice-select input.form-control').focus();
        @elseif (!$payment)
            $('#amount').focus();
        @endif

        $('.payment_date .input-group-addon').click(function() {
            toggleDatePicker('payment_date');
        });
	});

    function onFormSubmit(event) {
        $('#saveButton').attr('disabled', true);
    }

    function submitAction(action) {
        $('#action').val(action);
        $('.main-form').submit();
    }

    function onDeleteClick() {
        sweetConfirm(function() {
            submitAction('delete');
        });
    }

	</script>

@stop
