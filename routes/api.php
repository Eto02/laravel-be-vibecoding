<?php

foreach (['auth', 'payment', 'media'] as $domain) {
    require __DIR__."/api/{$domain}.php";
}
