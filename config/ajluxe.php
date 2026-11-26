<?php

return [
    /*
    | The staff portal's own address, e.g. "dashboard.ajluxeauto.co.tz".
    | When set, staff pages (/login, /dashboard, …) answer only there and give "not found" on the
    | public website, and the website does not answer on that address. Leave empty on a PC
    | (127.0.0.1:8000) to have both on one address.
    */
    'admin_domain' => env('ADMIN_DOMAIN') ? strtolower(trim(env('ADMIN_DOMAIN'))) : null,

    /*
    | Website opening time (shop timezone). Until then the public website shows a "coming soon"
    | page with a countdown, and switches itself to the real shop at this moment. Empty = open.
    | Staff can look at the real site early with the preview link shown on their Dashboard.
    */
    'launch_at' => env('LAUNCH_AT', '2026-11-27 17:00'),
];
