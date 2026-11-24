<?php

return [
    /*
    | The staff portal's own address, e.g. "dashboard.ajluxeauto.co.tz".
    | When set, staff pages (/login, /dashboard, …) answer only there and give "not found" on the
    | public website, and the website does not answer on that address. Leave empty on a PC
    | (127.0.0.1:8000) to have both on one address.
    */
    'admin_domain' => env('ADMIN_DOMAIN') ? strtolower(trim(env('ADMIN_DOMAIN'))) : null,
];
