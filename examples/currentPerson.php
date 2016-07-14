<?php

include_once '_includes.php';

$response = $fs->get('/platform/tree/current-person');

prettyPrint($response);