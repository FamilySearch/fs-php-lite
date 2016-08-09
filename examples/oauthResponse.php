<?php

include_once '_includes.php';
include_once '_header.php';

echo '<h2>OAuth Response</h2>';
echo '<h3>Access Token</h3>';
echo '<pre>', $fs->oauthResponse(), '</pre>';

include_once '_footer.php';