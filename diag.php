<?php
/* TEMPORARY DIAGNOSTIC — remove after use. Masks secret values (smtp_user/pass). */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        echo "\n[SHUTDOWN FATAL] {$e['message']} in {$e['file']}:{$e['line']}\n";
    }
});

echo "PHP " . PHP_VERSION . " (" . PHP_SAPI . ")\n";
$dir = __DIR__;
echo "dir: $dir\n\n";

$cp = $dir . '/config.php';
echo "config.php: exists=" . (file_exists($cp) ? 'Y' : 'N')
   . " readable=" . (is_readable($cp) ? 'Y' : 'N')
   . " size=" . (file_exists($cp) ? filesize($cp) : 0) . "\n";
try {
    $cfg = require $cp;
    echo "  require OK, type=" . gettype($cfg) . "\n";
    if (is_array($cfg)) {
        echo "  keys: " . implode(', ', array_keys($cfg)) . "\n";
        foreach (['smtp_host','smtp_port','smtp_secure','smtp_user','smtp_pass','from_email','from_name','to_email','to_name','send_confirmation'] as $k) {
            if (!array_key_exists($k, $cfg)) { echo "    $k = <MISSING>\n"; continue; }
            $secret = in_array($k, ['smtp_user','smtp_pass'], true);
            echo "    $k = " . ($secret ? "(len " . strlen((string)$cfg[$k]) . ")" : var_export($cfg[$k], true)) . "\n";
        }
    }
} catch (\Throwable $t) {
    echo "  require THREW " . get_class($t) . ": " . $t->getMessage() . " @ " . $t->getFile() . ":" . $t->getLine() . "\n";
}
echo "\n";

foreach (['PHPMailer.php','SMTP.php','Exception.php'] as $vf) {
    $p = $dir . '/vendor/PHPMailer/' . $vf;
    echo "vendor/$vf: exists=" . (file_exists($p) ? 'Y' : 'N')
       . " readable=" . (is_readable($p) ? 'Y' : 'N')
       . " size=" . (file_exists($p) ? filesize($p) : 0) . "\n";
    try {
        require $p;
        echo "  require OK\n";
    } catch (\Throwable $t) {
        echo "  require THREW " . get_class($t) . ": " . $t->getMessage() . " @ " . $t->getFile() . ":" . $t->getLine() . "\n";
    }
}
echo "\n";
echo "class PHPMailer: " . (class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? 'Y' : 'N') . "\n";
echo "class SMTP: " . (class_exists('PHPMailer\\PHPMailer\\SMTP') ? 'Y' : 'N') . "\n";
echo "class Exception: " . (class_exists('PHPMailer\\PHPMailer\\Exception') ? 'Y' : 'N') . "\n";
echo "\nDONE\n";
