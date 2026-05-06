<?php

foreach (['auth', 'user', 'payment', 'media'] as $domain) {
    require __DIR__."/api/{$domain}.php";
}
