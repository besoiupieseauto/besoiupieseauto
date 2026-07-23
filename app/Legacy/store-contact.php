<?php

declare(strict_types=1);

/**
 * Date contact oficiale magazin — o singură sursă pentru footer, contact și pagina preview.
 */
if (!function_exists('besoiu_store_phone_display')) {
    function besoiu_store_phone_display(): string
    {
        return '+40 726 498 573';
    }
}

if (!function_exists('besoiu_store_phone_short')) {
    function besoiu_store_phone_short(): string
    {
        return '0726 498 573';
    }
}

if (!function_exists('besoiu_store_phone_tel')) {
    function besoiu_store_phone_tel(): string
    {
        return 'tel:+40726498573';
    }
}

if (!function_exists('besoiu_store_address_line')) {
    function besoiu_store_address_line(): string
    {
        return 'Bulevardul General Ion Dragalina 23, 300162 Timișoara, România';
    }
}

if (!function_exists('besoiu_store_address_short')) {
    function besoiu_store_address_short(): string
    {
        return 'Bd. Gen. Ion Dragalina 23, Timișoara';
    }
}

if (!function_exists('besoiu_store_maps_url')) {
    function besoiu_store_maps_url(): string
    {
        return 'https://maps.google.com/?q=Bulevardul+General+Ion+Dragalina+23+300162+Timi%C8%99oara+Romania';
    }
}
