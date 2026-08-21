#!/usr/bin/env php
<?php

$venv = __DIR__ . '/../.venv';
$requirements = __DIR__ . '/../requirements.txt';

if (!is_file($requirements)) {
    fwrite(STDERR, "requirements.txt not found: {$requirements}\n");
    exit(1);
}

if (!is_dir($venv)) {
    passthru('python3 -m venv ' . escapeshellarg($venv), $exit);
    if ($exit !== 0) {
        fwrite(STDERR, "Failed to create Python virtual environment at {$venv}\n");
        exit($exit);
    }
}

$pip = $venv . '/bin/pip';
if (!is_executable($pip) && !file_exists($pip)) {
    fwrite(STDERR, "pip not found in virtual environment: {$pip}\n");
    exit(1);
}

passthru(escapeshellarg($pip) . ' install -r ' . escapeshellarg($requirements), $exit);
exit($exit);
