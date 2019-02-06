<?php
if (!option('bnomei.securityheaders.route.before')) {
    \Bnomei\SecurityHeaders::apply();
}
