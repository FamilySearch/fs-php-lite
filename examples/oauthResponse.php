<?php

include '_client.php';

echo $fs->oauthResponse();
echo '<pre>', print_r($fs->_lastResponse, true), '</pre>';