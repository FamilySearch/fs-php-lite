<?php

error_reporting(E_ALL);

// Load composer dependencies
require __DIR__ . '/../vendor/autoload.php';

// Configure VCR for integration tests
\VCR\VCR::configure()->addRequestMatcher('custom_headers', function(\VCR\Request $first, \VCR\Request $second){
    $firstHeaders = $first->getHeaders();
    $secondHeaders = $second->getHeaders();
    unset($firstHeaders['User-Agent']);
    unset($secondHeaders['User-Agent']);
    return count(array_diff_assoc($firstHeaders, $secondHeaders)) === 0;
});

\VCR\VCR::configure()->enableRequestMatchers(array('method','url','query_string','body','custom_headers'));
\VCR\VCR::configure()->setMode('once');
\VCR\VCR::configure()->setCassettePath('tests/fixtures');
\VCR\VCR::configure()->setBlackList(['vendor']);
\VCR\VCR::configure()->enableLibraryHooks(['curl']);
