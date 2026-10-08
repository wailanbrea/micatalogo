<?php

return [
    // Keep the first support inbox explicit until support agents are configurable
    // from the owner panel. The API still validates this on the server.
    'owner_email' => env('SUPPORT_OWNER_EMAIL', 'wailandkey@gmail.com'),
];
