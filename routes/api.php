<?php

foreach (['auth', 'user', 'merchant', 'payment', 'media'] as $domain) {
    require __DIR__."/api/{$domain}.php";
}
