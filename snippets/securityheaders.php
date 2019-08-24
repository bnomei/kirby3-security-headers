<?php

use Bnomei\SecurityHeaders;

if (!option('bnomei.securityheaders.route.before')) {
    SecurityHeaders::apply();
}
