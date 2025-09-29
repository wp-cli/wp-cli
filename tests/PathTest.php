<?php
// Standalone check for Windows path regex (no PHPUnit)

$cases = [
    'C:\wp\public/'   => true,
    'C:/wp/public/'   => true,
    'C:\wp\public'    => true,
    '/var/www/html/'  => false,
    './relative/path' => false,
];

foreach ($cases as $path => $expected) {
    $result = (bool) preg_match('#^[a-zA-Z]:(?:\\\\|/)#', $path);
    echo $path . " => " . ($result ? 'matched' : 'not matched') . " | expected: " . ($expected ? 'matched' : 'not matched') . PHP_EOL;
}
