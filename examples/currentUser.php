<?php

include_once '_includes.php';

$response = $fs->get('/platform/users/current');

prettyPrint($response);