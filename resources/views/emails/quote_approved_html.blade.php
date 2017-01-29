@extends('emails.master_user')

@section('markup')
    @if ($company->emailMarkupEnabled())
        @include('emails.partials.user_view_action')
    @endif
@stop

@section('body')
    <div>
        {{ trans('texts.email_salutation', ['name' => $userName]) }}
    </div>
    &nbsp;
    <div>
        {{ trans("texts.notification_quote_approved", ['amount' => $invoiceAmount, 'relation' => $relationName, 'invoice' => $invoiceNumber]) }}
    </div>
    &nbsp;
    <div>
        <center>
            @include('partials.email_button', [
                'link' => $invoiceLink,
                'field' => "view_{$entityType}",
                'color' => '#0b4d78',
            ])
        </center>
    </div>
    &nbsp;
    <div>
        {{ trans('texts.email_signature') }} <br/>
        {{ trans('texts.email_from') }}
    </div>
@stop