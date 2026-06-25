<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudflare API
    |--------------------------------------------------------------------------
    |
    | Used by the cloudflare:update-dns command to keep an A record in sync
    | with the server's current public IPv4 address.
    |
    | Create a token at Cloudflare → My Profile → API Tokens with
    | Zone → DNS → Edit for your zone (e.g. fiskkarta.com).
    |
    */

    'api_token' => env('CLOUDFLARE_API_TOKEN'),

    'zone_id' => env('CLOUDFLARE_ZONE_ID'),

    'dns_record' => env('CLOUDFLARE_DNS_RECORD', 'bbq.fiskkarta.com'),

    'ip_check_url' => env('CLOUDFLARE_IP_CHECK_URL', 'https://api.ipify.org'),

    'state_file' => storage_path('app/cloudflare-ddns.json'),

];
