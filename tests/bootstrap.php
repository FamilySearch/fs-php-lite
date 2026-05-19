<?php

error_reporting(E_ALL);

// Load composer dependencies
require __DIR__ . '/../vendor/autoload.php';

// Configure VCR for integration tests
// Use simple matching: only method and URL (ignore headers and body differences)
// This allows cassettes recorded with old PHP/curl versions to work with new versions
\VCR\VCR::configure()->enableRequestMatchers(array('method','url'));
// Use 'once' mode: use cassette if available, otherwise record new episodes
// This allows tests to work with either pre-recorded cassettes or live API
\VCR\VCR::configure()->setMode('once');
\VCR\VCR::configure()->setCassettePath('tests/fixtures');
\VCR\VCR::configure()->setBlackList(['vendor']);
\VCR\VCR::configure()->enableLibraryHooks(['curl']);
