<?php

include_once '_includes.php';
include_once '_header.php';

echo '<h2>Is Authenticated?</h2>';
if ($fs->isAuthenticated()) {
  echo 'Authenticated';
} else {
  echo 'Not Authenticated';
}

include_once '_footer.php';