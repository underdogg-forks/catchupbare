@extends('header')

@section('head')
    @parent

        <style type="text/css">
            .iframe_url {
                display: none;
            }
            .input-group-addon div.checkbox {
                display: inline;
            }
            .tab-content .pad-checkbox span.input-group-addon {
                padding-right: 30px;
            }
        </style>
@stop

@section('content')
	@parent
    @include('companies.nav', ['selected' => COMPANY_INVOICE_SETTINGS, 'advanced' => true])

    {!! Former::open()->rules(['iframe_url' => 'url'])->addClass('warn-on-exit') !!}
    {{ Former::populate($company) }}
    {{ Former::populateField('auto_convert_quote', intval($company->auto_convert_quote)) }}
    {{ Former::populateField('custom_invoice_taxes1', intval($company->custom_invoice_taxes1)) }}
    {{ Former::populateField('custom_invoice_taxes2', intval($company->custom_invoice_taxes2)) }}
    {{ Former::populateField('share_counter', intval($company->share_counter)) }}

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.generated_numbers') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">

            <div role="tabpanel">
                <ul class="nav nav-tabs" role="tablist" style="border: none">
                    <li role="presentation" class="active">
                        <a href="#invoice_number" aria-controls="invoice_number" role="tab" data-toggle="tab">{{ trans('texts.invoice_number') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#quote_number" aria-controls="quote_number" role="tab" data-toggle="tab">{{ trans('texts.quote_number') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#client_number" aria-controls="client_number" role="tab" data-toggle="tab">{{ trans('texts.client_number') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#recurring_invoice_number" aria-controls="recurring_invoice_number" role="tab" data-toggle="tab">{{ trans('texts.recurring_invoice_number') }}</a>
                    </li>
                </ul>
            </div>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="invoice_number">
                    <div class="panel-body">
                        {!! Former::inline_radios('invoice_number_type')
                                ->onchange('onInvoiceNumberTypeChange()')
                                ->label(trans('texts.type'))
                                ->radios([
                                    trans('texts.prefix') => ['value' => 'prefix', 'name' => 'invoice_number_type'],
                                    trans('texts.pattern') => ['value' => 'pattern', 'name' => 'invoice_number_type'],
                                ])->check($company->invoice_number_pattern ? 'pattern' : 'prefix') !!}

                        {!! Former::text('invoice_number_prefix')
                                ->addGroupClass('invoice-prefix')
                                ->label(trans('texts.prefix')) !!}
                        {!! Former::text('invoice_number_pattern')
                                ->appendIcon('question-sign')
                                ->addGroupClass('invoice-pattern')
                                ->label(trans('texts.pattern'))
                                ->addGroupClass('number-pattern') !!}
                        {!! Former::text('invoice_number_padding') !!}
                        {!! Former::text('invoice_number_counter')
                                ->label(trans('texts.counter'))
                                ->help(trans('texts.invoice_number_help') . ' ' .
                                    trans('texts.next_invoice_number', ['number' => $company->previewNextInvoiceNumber()])) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="quote_number">
                    <div class="panel-body">
                        {!! Former::inline_radios('quote_number_type')
                                ->onchange('onQuoteNumberTypeChange()')
                                ->label(trans('texts.type'))
                                ->radios([
                                    trans('texts.prefix') => ['value' => 'prefix', 'name' => 'quote_number_type'],
                                    trans('texts.pattern') => ['value' => 'pattern', 'name' => 'quote_number_type'],
                                ])->check($company->quote_number_pattern ? 'pattern' : 'prefix') !!}

                        {!! Former::text('quote_number_prefix')
                                ->addGroupClass('quote-prefix')
                                ->label(trans('texts.prefix')) !!}
                        {!! Former::text('quote_number_pattern')
                                ->appendIcon('question-sign')
                                ->addGroupClass('quote-pattern')
                                ->addGroupClass('number-pattern')
                                ->label(trans('texts.pattern')) !!}
                        {!! Former::text('quote_number_counter')
                                ->label(trans('texts.counter'))
                                ->addGroupClass('pad-checkbox')
                                ->append(Former::checkbox('share_counter')->raw()->value(1)
                                ->onclick('setQuoteNumberEnabled()') . ' ' . trans('texts.share_invoice_counter'))
                                ->help(trans('texts.quote_number_help') . ' ' .
                                    trans('texts.next_quote_number', ['number' => $company->previewNextInvoiceNumber(ENTITY_QUOTE)])) !!}


                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="client_number">
                    <div class="panel-body">
                        {!! Former::checkbox('client_number_enabled')
                                ->label('client_number')
                                ->onchange('onClientNumberEnabled()')
                                ->text('enable')
                                ->value(1)
                                ->check($company->client_number_counter > 0) !!}

                        <div id="clientNumberDiv" style="display:none">

                            {!! Former::inline_radios('client_number_type')
                                    ->onchange('onClientNumberTypeChange()')
                                    ->label(trans('texts.type'))
                                    ->radios([
                                        trans('texts.prefix') => ['value' => 'prefix', 'name' => 'client_number_type'],
                                        trans('texts.pattern') => ['value' => 'pattern', 'name' => 'client_number_type'],
                                    ])->check($company->client_number_pattern ? 'pattern' : 'prefix') !!}

                            {!! Former::text('client_number_prefix')
                                    ->addGroupClass('relation-prefix')
                                    ->label(trans('texts.prefix')) !!}
                            {!! Former::text('client_number_pattern')
                                    ->appendIcon('question-sign')
                                    ->addGroupClass('relation-pattern')
                                    ->addGroupClass('number-pattern')
                                    ->label(trans('texts.pattern')) !!}
                            {!! Former::text('client_number_counter')
                                    ->label(trans('texts.counter'))
                                    ->addGroupClass('pad-checkbox')
                                    ->help(trans('texts.client_number_help') . ' ' .
                                        trans('texts.next_client_number', ['number' => $company->getNextNumber() ?: '0001'])) !!}

                        </div>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="recurring_invoice_number">
                    <div class="panel-body">

                        {!! Former::text('recurring_invoice_number_prefix')
                                ->label(trans('texts.prefix'))
                                ->help(trans('texts.recurring_invoice_number_prefix_help')) !!}

                    </div>
                </div>
            </div>

        </div>
    </div>


    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.custom_fields') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">

            <div role="tabpanel">
                <ul class="nav nav-tabs" role="tablist" style="border: none">
                    <li role="presentation" class="active">
                        <a href="#relation_fields" aria-controls="relation_fields" role="tab" data-toggle="tab">{{ trans('texts.relation_fields') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#corporation_fields" aria-controls="corporation_fields" role="tab" data-toggle="tab">{{ trans('texts.corporation_fields') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#invoice_fields" aria-controls="invoice_fields" role="tab" data-toggle="tab">{{ trans('texts.invoice_fields') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#invoice_item_fields" aria-controls="invoice_item_fields" role="tab" data-toggle="tab">{{ trans('texts.invoice_item_fields') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#invoice_charges" aria-controls="invoice_charges" role="tab" data-toggle="tab">{{ trans('texts.invoice_charges') }}</a>
                    </li>
                </ul>
            </div>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="relation_fields">
                    <div class="panel-body">

                        {!! Former::text('custom_client_label1')
                                ->label(trans('texts.field_label')) !!}
                        {!! Former::text('custom_client_label2')
                                ->label(trans('texts.field_label'))
                                ->help(trans('texts.custom_relation_fields_helps')) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="corporation_fields">
                    <div class="panel-body">

                        {!! Former::text('custom_label1')
                                ->label(trans('texts.field_label')) !!}
                        {!! Former::text('custom_value1')
                                ->label(trans('texts.field_value')) !!}
                        <p>&nbsp;</p>
                        {!! Former::text('custom_label2')
                                ->label(trans('texts.field_label')) !!}
                        {!! Former::text('custom_value2')
                                ->label(trans('texts.field_value'))
                                ->help(trans('texts.custom_account_fields_helps')) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="invoice_fields">
                    <div class="panel-body">

                        {!! Former::text('custom_invoice_text_label1')
                                ->label(trans('texts.field_label')) !!}
                        {!! Former::text('custom_invoice_text_label2')
                                ->label(trans('texts.field_label'))
                                ->help(trans('texts.custom_invoice_fields_helps')) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="invoice_item_fields">
                    <div class="panel-body">

                        {!! Former::text('custom_invoice_item_label1')
                                ->label(trans('texts.field_label')) !!}
                        {!! Former::text('custom_invoice_item_label2')
                                ->label(trans('texts.field_label'))
                                ->help(trans('texts.custom_invoice_item_fields_help')) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="invoice_charges">
                    <div class="panel-body">

                        {!! Former::text('custom_invoice_label1')
                                ->label(trans('texts.field_label'))
                                ->addGroupClass('pad-checkbox')
                                ->append(Former::checkbox('custom_invoice_taxes1')
                                ->value(1)
                                ->raw() . trans('texts.charge_taxes')) !!}
                        {!! Former::text('custom_invoice_label2')
                                ->label(trans('texts.field_label'))
                                ->addGroupClass('pad-checkbox')
                                ->append(Former::checkbox('custom_invoice_taxes2')
                                ->value(1)
                                ->raw() . trans('texts.charge_taxes'))
                                ->help(trans('texts.custom_invoice_charges_helps')) !!}

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.quote_settings') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">
            {!! Former::checkbox('auto_convert_quote')
                    ->text(trans('texts.enable'))
                    ->blockHelp(trans('texts.auto_convert_quote_help'))
                    ->value(1) !!}
        </div>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.default_messages') !!}</h3>
      </div>
        <div class="panel-body form-padding-right">

            <div role="tabpanel">
                <ul class="nav nav-tabs" role="tablist" style="border: none">
                    <li role="presentation" class="active"><a href="#invoice_terms" aria-controls="invoice_terms" role="tab" data-toggle="tab">{{ trans('texts.invoice_terms') }}</a></li>
                    <li role="presentation"><a href="#invoice_footer" aria-controls="invoice_footer" role="tab" data-toggle="tab">{{ trans('texts.invoice_footer') }}</a></li>
                    <li role="presentation"><a href="#quote_terms" aria-controls="quote_terms" role="tab" data-toggle="tab">{{ trans('texts.quote_terms') }}</a></li>
                </ul>
            </div>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="invoice_terms">
                    <div class="panel-body">
                        {!! Former::textarea('invoice_terms')
                                ->label(trans('texts.default_invoice_terms'))
                                ->rows(4) !!}
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="invoice_footer">
                    <div class="panel-body">
                        {!! Former::textarea('invoice_footer')
                                ->label(trans('texts.default_invoice_footer'))
                                ->rows(4) !!}
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="quote_terms">
                    <div class="panel-body">
                        {!! Former::textarea('quote_terms')
                                ->label(trans('texts.default_quote_terms'))
                                ->rows(4) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>



    @if (Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS))
        <center>
            {!! Button::success(trans('texts.save'))->large()->submit()->appendIcon(Icon::create('floppy-disk')) !!}
        </center>
    @endif

    <div class="modal fade" id="patternHelpModal" tabindex="-1" role="dialog" aria-labelledby="patternHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="min-width:150px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="patternHelpModalLabel">{{ trans('texts.pattern_help_title') }}</h4>
                </div>

                <div class="modal-body">
                    <p>{{ trans('texts.pattern_help_1') }}</p>
                    <p>{{ trans('texts.pattern_help_2') }}</p>
                    <ul>
                        @foreach (\App\Models\Invoice::$patternFields as $field)
                            @if ($field == 'date:')
                                <li>{$date:format} - {!! link_to(PHP_DATE_FORMATS, trans('texts.see_options'), ['target' => '_blank']) !!}</li>
                            @else
                                <li>{${{ $field }}}</li>
                            @endif
                        @endforeach
                    </ul>
                    <p>{{ trans('texts.pattern_help_3', [
                            'example' => '{$year}-{$counter}',
                            'value' => date('Y') . '-0001'
                        ]) }}</p>
                </div>

                <div class="modal-footer" style="margin-top: 0px">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
                </div>

            </div>
        </div>
    </div>


	{!! Former::close() !!}


	<script type="text/javascript">

  	function setQuoteNumberEnabled() {
			var disabled = $('#share_counter').prop('checked');
			$('#quote_number_counter').prop('disabled', disabled);
			$('#quote_number_counter').val(disabled ? '' : '{!! $company->quote_number_counter !!}');
		}

    function onInvoiceNumberTypeChange() {
        var val = $('input[name=invoice_number_type]:checked').val()
        if (val == 'prefix') {
            $('.invoice-prefix').show();
            $('.invoice-pattern').hide();
        } else {
            $('.invoice-prefix').hide();
            $('.invoice-pattern').show();
        }
    }

    function onQuoteNumberTypeChange() {
        var val = $('input[name=quote_number_type]:checked').val()
        if (val == 'prefix') {
            $('.quote-prefix').show();
            $('.quote-pattern').hide();
        } else {
            $('.quote-prefix').hide();
            $('.quote-pattern').show();
        }
    }

    function onClientNumberTypeChange() {
        var val = $('input[name=client_number_type]:checked').val()
        if (val == 'prefix') {
            $('.relation-prefix').show();
            $('.relation-pattern').hide();
        } else {
            $('.relation-prefix').hide();
            $('.relation-pattern').show();
        }
    }

    function onClientNumberEnabled() {
        var enabled = $('#client_number_enabled').is(':checked');
        if (enabled) {
            $('#clientNumberDiv').show();
            $('#client_number_counter').val({{ $company->client_number_counter ?: 1 }});
        } else {
            $('#clientNumberDiv').hide();
            $('#client_number_counter').val(0);
        }
    }

    $('.number-pattern .input-group-addon').click(function() {
        $('#patternHelpModal').modal('show');
    });

    $(function() {
    	setQuoteNumberEnabled();
        onInvoiceNumberTypeChange();
        onQuoteNumberTypeChange();
        onClientNumberTypeChange();
        onClientNumberEnabled();
    });

	</script>


@stop

@section('onReady')
    $('#custom_invoice_label1').focus();
@stop
