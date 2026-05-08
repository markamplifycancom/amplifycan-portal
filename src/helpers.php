<?php
/**
 * Global helpers — kept outside any namespace so templates can call them as `e($x)` without qualification.
 */

if (!function_exists('e')) {
    function e(?string $s): string
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('pill_class')) {
    function pill_class(string $status): string
    {
        return match ($status) {
            'Submitted'     => 'pill-blue',
            'In Production' => 'pill-yellow',
            'Shipped'       => 'pill-blue',
            'Delivered'     => 'pill-green',
            'Invoiced'      => 'pill-gray',
            default         => 'pill-gray',
        };
    }
}
