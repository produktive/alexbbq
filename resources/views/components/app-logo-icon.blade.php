@php
    $flamePaths = trim(preg_replace('/<\/?svg[^>]*>/', '', file_get_contents(resource_path('svg/flame-mark.svg'))));
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1254 1254" {{ $attributes }}>
    <rect class="fill-white dark:fill-zinc-900" width="1254" height="1254" rx="274" ry="274"/>
    {!! $flamePaths !!}
</svg>
