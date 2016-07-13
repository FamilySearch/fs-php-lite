<?php

include '_client.php';

echo '<pre>', print_r($fs->oauthResponse(), true), '</pre>';
echo '<pre>', print_r($fs->_lastResponse, true), '</pre>';