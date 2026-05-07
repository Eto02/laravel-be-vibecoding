<?php

foreach (['auth', 'user', 'merchant', 'product', 'payment', 'media'] as $domain) {
    require __DIR__."/api/{$domain}.php";
}
