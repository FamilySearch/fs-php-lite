<?php

include_once '_includes.php';
include_once '_header.php';

$response = $fs->get('/platform/users/current');

echo '<h2>Current User Response</h2>';
prettyPrint($response);

include_once '_footer.php';