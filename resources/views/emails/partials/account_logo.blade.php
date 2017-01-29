@if ($company->hasLogo())
    @if ($company->website)
        <a href="{{ $company->website }}" style="color: #19BB40; text-decoration: underline;">
    @endif

    <img src="{{ isset($message) ? $message->embed($company->getLogoPath()) : $company->getLogoURL() }}" style="max-height:50px; max-width:140px; margin-left: 33px;" alt="{{ trans('texts.logo') }}"/>

    @if ($company->website)
        </a>
    @endif
@endif
