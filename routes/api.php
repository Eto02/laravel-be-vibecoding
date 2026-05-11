<?php

foreach (['auth', 'user', 'merchant', 'product', 'cart', 'order', 'payment', 'media'] as $domain) {
    require __DIR__."/api/{$domain}.php";
}
