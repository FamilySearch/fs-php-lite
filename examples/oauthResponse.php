<?php

include_once '_includes.php';
include_once '_header.php';

echo '<h2>OAuth Response</h2>';
prettyPrint($fs->oauthResponse());

include_once '_footer.php';