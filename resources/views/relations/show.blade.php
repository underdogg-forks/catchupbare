@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/select2.css') }}" rel="stylesheet" type="text/css"/>

    @if ($relation->showMap())
        <style>
            #map {
                width: 100%;
                height: 200px;
                border-width: 1px;
                border-style: solid;
                border-color: #ddd;
            }
        </style>

        <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}"></script>
    @endif
@stop


@section('content')

    <div class="row">
        <div class="col-md-7">
            <ol class="breadcrumb">
                <li>{{ link_to('/relations', trans('texts.relations')) }}</li>
                <li class='active'>{{ $relation->getDisplayName() }}</li> {!! $relation->present()->statusLabel !!}
            </ol>
        </div>
        <div class="col-md-5">
            <div class="pull-right">
                {!! Former::open('relations/bulk')->addClass('mainForm') !!}
                <div style="display:none">
                    {!! Former::text('action') !!}
                    {!! Former::text('public_id')->value($relation->id) !!}
                </div>

                @if ($gatewayLink)
                    {!! Button::normal(trans('texts.view_in_gateway', ['gateway'=>$gatewayName]))
                            ->asLinkTo($gatewayLink)
                            ->withAttributes(['target' => '_blank']) !!}
                @endif

                @if ( ! $relation->is_deleted)
                    @can('edit', $relation)
                    {!! DropdownButton::normal(trans('texts.edit_relation'))
                        ->withAttributes(['class'=>'normalDropDown'])
                        ->withContents([
                          ($relation->trashed() ? false : ['label' => trans('texts.archive_client'), 'url' => "javascript:onArchiveClick()"]),
                          ['label' => trans('texts.delete_client'), 'url' => "javascript:onDeleteClick()"],
                        ]
                      )->split() !!}
                    @endcan
                    @if ( ! $relation->trashed())
                        @can('create', ENTITY_INVOICE)
                        {!! DropdownButton::primary(trans('texts.view_statement'))
                                ->withAttributes(['class'=>'primaryDropDown'])
                                ->withContents($actionLinks)->split() !!}
                        @endcan
                    @endif
                @endif

                @if ($relation->trashed())
                    @can('edit', $relation)
                    {!! Button::primary(trans('texts.restore_client'))
                            ->appendIcon(Icon::create('cloud-download'))
                            ->withAttributes(['onclick' => 'onRestoreClick()']) !!}
                    @endcan
                @endif


                {!! Former::close() !!}

            </div>
        </div>
    </div>

    @if ($relation->last_login > 0)
        <h3 style="margin-top:0px">
            <small>
                {{ trans('texts.last_logged_in') }} {{ Utils::timestampToDateTimeString(strtotime($relation->last_login)) }}
            </small>
        </h3>
    @endif

    <div class="panel panel-default">
        <div class="panel-body">
            <div class="row">

                <div class="col-md-3">
                    <h3>{{ trans('texts.details') }}</h3>
                    @if ($relation->id_number)
                        <p><i class="fa fa-id-number"
                              style="width: 20px"></i>{{ trans('texts.id_number').': '.$relation->id_number }}</p>
                    @endif
                    @if ($relation->vat_number)
                        <p><i class="fa fa-vat-number"
                              style="width: 20px"></i>{{ trans('texts.vat_number').': '.$relation->vat_number }}</p>
                    @endif

                    @if ($relation->address1)
                        {{ $relation->address1 }}<br/>
                    @endif
                    @if ($relation->address2)
                        {{ $relation->address2 }}<br/>
                    @endif
                    @if ($relation->getCityState())
                        {{ $relation->getCityState() }}<br/>
                    @endif
                    @if ($relation->country)
                        {{ $relation->country->name }}<br/>
                    @endif

                    @if ($relation->company->custom_client_label1 && $relation->custom_value1)
                        {{ $relation->company->custom_client_label1 . ': ' . $relation->custom_value1 }}<br/>
                    @endif
                    @if ($relation->company->custom_client_label2 && $relation->custom_value2)
                        {{ $relation->company->custom_client_label2 . ': ' . $relation->custom_value2 }}<br/>
                    @endif

                    @if ($relation->work_phone)
                        <i class="fa fa-phone" style="width: 20px"></i>{{ $relation->work_phone }}
                    @endif

                    @if ($relation->private_notes)
                        <p><i>{{ $relation->private_notes }}</i></p>
                    @endif

                    @if ($relation->client_industry)
                        {{ $relation->client_industry->name }}<br/>
                    @endif
                    @if ($relation->client_size)
                        {{ $relation->client_size->name }}<br/>
                    @endif

                    @if ($relation->website)
                        <p>{!! Utils::formatWebsite($relation->website) !!}</p>
                    @endif

                    @if ($relation->language)
                        <p><i class="fa fa-language" style="width: 20px"></i>{{ $relation->language->name }}</p>
                    @endif

                    <p>{{ $relation->payment_terms ? trans('texts.payment_terms') . ": Net " . $relation->payment_terms : '' }}</p>
                </div>

                <div class="col-md-3">
                    <h3>{{ trans('texts.contacts') }}</h3>
                    @foreach ($relation->contacts as $contact)
                        @if ($contact->first_name || $contact->last_name)
                            <b>{{ $contact->first_name.' '.$contact->last_name }}</b><br/>
                        @endif
                        @if ($contact->email)
                            <i class="fa fa-envelope"
                               style="width: 20px"></i>{!! HTML::mailto($contact->email, $contact->email) !!}<br/>
                        @endif
                        @if ($contact->phone)
                            <i class="fa fa-phone" style="width: 20px"></i>{{ $contact->phone }}<br/>
                        @endif
                        @if (Auth::user()->confirmed && $relation->company->enable_client_portal)
                            <i class="fa fa-dashboard" style="width: 20px"></i><a href="{{ $contact->link }}"
                                                                                  target="_blank">{{ trans('texts.view_client_portal') }}</a>
                            <br/>
                        @endif
                    @endforeach
                </div>

                <div class="col-md-4">
                    <h3>{{ trans('texts.standing') }}
                        <table class="table" style="width:100%">
                            <tr>
                                <td>
                                    <small>{{ trans('texts.paid_to_date') }}</small>
                                </td>
                                <td style="text-align: right">{{ Utils::formatMoney($relation->paid_to_date, $relation->getCurrencyId()) }}</td>
                            </tr>
                            <tr>
                                <td>
                                    <small>{{ trans('texts.balance') }}</small>
                                </td>
                                <td style="text-align: right">{{ Utils::formatMoney($relation->balance, $relation->getCurrencyId()) }}</td>
                            </tr>
                            @if ($credit > 0)
                                <tr>
                                    <td>
                                        <small>{{ trans('texts.credit') }}</small>
                                    </td>
                                    <td style="text-align: right">{{ Utils::formatMoney($credit, $relation->getCurrencyId()) }}</td>
                                </tr>
                            @endif
                        </table>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    @if ($relation->showMap())
        <div id="map"></div>
        <br/>
    @endif

    <ul class="nav nav-tabs nav-justified">
        {!! Form::tab_link('#activity', trans('texts.activity'), true) !!}
        @if ($hasTasks && Utils::isPro())
            {!! Form::tab_link('#tasks', trans('texts.tasks')) !!}
        @endif
        @if ($hasQuotes && Utils::isPro())
            {!! Form::tab_link('#quotes', trans('texts.quotes')) !!}
        @endif
        @if ($hasRecurringInvoices)
            {!! Form::tab_link('#recurring_invoices', trans('texts.recurring')) !!}
        @endif
        {!! Form::tab_link('#invoices', trans('texts.invoices')) !!}
        {!! Form::tab_link('#payments', trans('texts.payments')) !!}
        {!! Form::tab_link('#credits', trans('texts.credits')) !!}
    </ul><br/>

    <div class="tab-content">

        <div class="tab-pane active" id="activity">
            {!! Datatable::table()
                ->addColumn(
                    trans('texts.date'),
                    trans('texts.message'),
                    trans('texts.balance'),
                    trans('texts.adjustment'))
                ->setUrl(url('api/activities/'. $relation->id))
                ->setCustomValues('entityType', 'activity')
                ->setCustomValues('clientId', $relation->id)
                ->setCustomValues('rightAlign', [2, 3])
                ->setOptions('sPaginationType', 'bootstrap')
                ->setOptions('bFilter', false)
                ->setOptions('aaSorting', [['0', 'desc']])
                ->render('datatable') !!}
        </div>

        @if ($hasTasks)
            <div class="tab-pane" id="tasks">
                @include('list', [
                    'entityType' => ENTITY_TASK,
                    'datatable' => new \App\Ninja\Datatables\TaskDatatable(true, true),
                    'clientId' => $relation->id,
                ])
            </div>
        @endif


        @if (Utils::hasFeature(FEATURE_QUOTES) && $hasQuotes)
            <div class="tab-pane" id="quotes">
                @include('list', [
                    'entityType' => ENTITY_QUOTE,
                    'datatable' => new \App\Ninja\Datatables\InvoiceDatatable(true, true, ENTITY_QUOTE),
                    'clientId' => $relation->id,
                ])
            </div>
        @endif

        @if ($hasRecurringInvoices)
            <div class="tab-pane" id="recurring_invoices">
                @include('list', [
                    'entityType' => ENTITY_RECURRING_INVOICE,
                    'datatable' => new \App\Ninja\Datatables\RecurringInvoiceDatatable(true, true),
                    'clientId' => $relation->id,
                ])
            </div>
        @endif

        <div class="tab-pane" id="invoices">
            @include('list', [
                'entityType' => ENTITY_INVOICE,
                'datatable' => new \App\Ninja\Datatables\InvoiceDatatable(true, true),
                'clientId' => $relation->id,
            ])
        </div>

        <div class="tab-pane" id="payments">
            @include('list', [
                'entityType' => ENTITY_PAYMENT,
                'datatable' => new \App\Ninja\Datatables\PaymentDatatable(true, true),
                'clientId' => $relation->id,
            ])
        </div>

        <div class="tab-pane" id="credits">
            @include('list', [
                'entityType' => ENTITY_CREDIT,
                'datatable' => new \App\Ninja\Datatables\CreditDatatable(true, true),
                'clientId' => $relation->id,
            ])
        </div>

    </div>

    <script type="text/javascript">

        var loadedTabs = {};

        $(function () {
            $('.normalDropDown:not(.dropdown-toggle)').click(function () {
                window.location = '{{ URL::to('relations/' . $relation->id . '/edit') }}';
            });
            $('.primaryDropDown:not(.dropdown-toggle)').click(function () {
                window.location = '{{ URL::to('relations/statement/' . $relation->id ) }}';
            });

            // load datatable data when tab is shown and remember last tab selected
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                var target = $(e.target).attr("href") // activated tab
                target = target.substring(1);
                localStorage.setItem('client_tab', target);
                if (!loadedTabs.hasOwnProperty(target)) {
                    loadedTabs[target] = true;
                    window['load_' + target]();
                }
            });

            var tab = window.location.hash || (localStorage.getItem('client_tab') || '');
            tab = tab.replace('#', '');
            var selector = '.nav-tabs a[href="#' + tab + '"]';
            if (tab && tab != 'activity' && $(selector).length) {
                $(selector).tab('show');
            } else {
                window['load_activity']();
            }
        });

        function onArchiveClick() {
            $('#action').val('archive');
            $('.mainForm').submit();
        }

        function onRestoreClick() {
            $('#action').val('restore');
            $('.mainForm').submit();
        }

        function onDeleteClick() {
            sweetConfirm(function () {
                $('#action').val('delete');
                $('.mainForm').submit();
            });
        }

        @if ($relation->showMap())
        function initialize() {
            var mapCanvas = document.getElementById('map');
            var mapOptions = {
                zoom: {{ DEFAULT_MAP_ZOOM }},
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                zoomControl: true,
            };

            var map = new google.maps.Map(mapCanvas, mapOptions)
            var address = "{{ "{$relation->address1} {$relation->address2} {$relation->city} {$relation->state} {$relation->postal_code} " . ($relation->country ? $relation->country->name : '') }}";

            geocoder = new google.maps.Geocoder();
            geocoder.geocode({'address': address}, function (results, status) {
                if (status == google.maps.GeocoderStatus.OK) {
                    if (status != google.maps.GeocoderStatus.ZERO_RESULTS) {
                        var result = results[0];
                        map.setCenter(result.geometry.location);

                        var infowindow = new google.maps.InfoWindow(
                                {
                                    content: '<b>' + result.formatted_address + '</b>',
                                    size: new google.maps.Size(150, 50)
                                });

                        var marker = new google.maps.Marker({
                            position: result.geometry.location,
                            map: map,
                            title: address,
                        });
                        google.maps.event.addListener(marker, 'click', function () {
                            infowindow.open(map, marker);
                        });
                    } else {
                        $('#map').hide();
                    }
                } else {
                    $('#map').hide();
                }
            });
        }

        google.maps.event.addDomListener(window, 'load', initialize);
        @endif

    </script>

@stop