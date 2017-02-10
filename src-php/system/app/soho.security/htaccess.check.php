<?php

// Base directory
$base = realpath(dirname(__FILE__) . '/../../../') . '/';

// Include library
include $base . 'system/app/soho.security/htaccess.lib.php';

// Get current state
$data = htaccess_audit_raw($base);

// Display current state
echo "Allowed directories:\n";
function display_htaccess_audit_cli(&$dir) {
    if ($dir['allow']) echo " {$dir['dir']}\n";
    if (isset($dir['sub'])) {
        foreach ($dir['sub'] as $sub) {
            display_htaccess_audit_cli($sub);
        }
    }
}
display_htaccess_audit_cli($data);