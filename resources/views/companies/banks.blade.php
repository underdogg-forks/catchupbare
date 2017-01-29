@extends('header')

@section('content')
@parent
@include('companies.nav', ['selected' => COMPANY_BANKS])

@if (Auth::user()->hasFeature(FEATURE_EXPENSES))
    <div class="pull-right">
        {!! Button::normal(trans('texts.import_ofx'))
            ->asLinkTo(URL::to('/bank_accs/import_ofx'))
            ->appendIcon(Icon::create('open')) !!}
        {!! Button::primary(trans('texts.add_bank_acc'))
            ->asLinkTo(URL::to('/bank_accs/create'))
            ->appendIcon(Icon::create('plus-sign')) !!}
    </div>
@endif

@include('partials.bulk_form', ['entityType' => ENTITY_BANK_COMPANY])

{!! Datatable::table()
    ->addColumn(
        trans('texts.name'),
        trans('texts.integration_type'),
        trans('texts.action'))
    ->setUrl(url('api/bank_accs/'))
    ->setOptions('sPaginationType', 'bootstrap')
    ->setOptions('bFilter', false)
    ->setOptions('bAutoWidth', false)
    ->setOptions('aoColumns', [[ "sWidth"=> "50%" ], [ "sWidth"=> "30%" ], ["sWidth"=> "20%"]])
    ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[2]]])
    ->render('datatable') !!}

<script>
    window.onDatatableReady = actionListHandler;
</script>

@stop
