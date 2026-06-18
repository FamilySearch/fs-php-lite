<?php

error_reporting(E_ALL);

// Load composer dependencies
require __DIR__ . '/../vendor/autoload.php';

// Configure VCR for integration tests
// Only match on method, url, and body (not headers since User-Agent varies)
\VCR\VCR::configure()->enableRequestMatchers(array('method','url','body'));
\VCR\VCR::configure()->setMode('once');
\VCR\VCR::configure()->setCassettePath('tests/fixtures');
\VCR\VCR::configure()->setBlackList(['vendor']);
\VCR\VCR::configure()->enableLibraryHooks(['curl']);
