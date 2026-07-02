@php
    $flamePaths = trim(preg_replace('/<\/?svg[^>]*>/', '', file_get_contents(resource_path('svg/flame-mark.svg'))));
@endphp

<img
    src="/pwa-icon-512.png"
    alt=""
    {{ $attributes->class(['hidden dark:block rounded-md']) }}
/>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1254 1254" {{ $attributes->class(['dark:hidden']) }}>
    <rect class="fill-white" width="1254" height="1254" rx="274" ry="274"/>
    {!! $flamePaths !!}
</svg>
