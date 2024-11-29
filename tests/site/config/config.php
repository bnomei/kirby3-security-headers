<?php

return [
    'debug' => true,

    'bnomei.securityheaders.enabled' => true, // force even on http localhost

    'bnomei.securityheaders.loader' => function () {
        return kirby()->roots()->config().'/csp.json';
    },
];
