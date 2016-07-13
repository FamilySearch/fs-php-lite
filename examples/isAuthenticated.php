<?php

include '_client.php';

if ($fs->isAuthenticated()) {
  echo 'Authenticated';
} else {
  echo 'Not Authenticated';
  echo '<pre>',print_r($_SESSION, true),'<pre>';
}