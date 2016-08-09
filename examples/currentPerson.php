<?php

include_once '_includes.php';
include_once '_header.php';

$response = $fs->get('/platform/tree/current-person');

echo '<h2>Current Person Response</h2>';
prettyPrint($response);

include_once '_footer.php';