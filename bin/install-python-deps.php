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

$python = $venv . '/bin/python';
if (!is_executable($python)) {
    fwrite(STDERR, "Python interpreter not found or not executable in virtual environment: {$python}\n");
    exit(1);
}

// Ensure pip is available inside the venv. Some distributions ship venv without ensurepip/pip.
passthru(escapeshellarg($python) . ' -m ensurepip --upgrade', $exit);
if ($exit !== 0) {
    fwrite(STDERR, "Failed to bootstrap pip in virtual environment at {$venv}\n");
    exit($exit);
}

$pip = $venv . '/bin/pip';
if (!is_executable($pip)) {
    fwrite(STDERR, "pip not found or not executable in virtual environment: {$pip}\n");
    exit(1);
}

passthru(escapeshellarg($pip) . ' install -r ' . escapeshellarg($requirements), $exit);
exit($exit);
