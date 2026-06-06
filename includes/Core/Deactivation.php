<?php

namespace MXPOSPro\Core;

defined('ABSPATH') || exit;

class Deactivation
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
