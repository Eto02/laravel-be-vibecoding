<?php

foreach (['auth', 'user', 'merchant', 'product', 'cart', 'payment', 'media'] as $domain) {
    require __DIR__."/api/{$domain}.php";
}
