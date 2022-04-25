<?php

return [
    'debug' => true,
    'bnomei.securityheaders.enabled' => 'force', // force even on http localhost

    'bnomei.securityheaders.loader' => function () {
        return kirby()->roots()->site() . '/loader-test.json';
    },
];
