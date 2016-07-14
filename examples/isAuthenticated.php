<?php

include_once '_includes.php';

if ($fs->isAuthenticated()) {
  echo 'Authenticated';
} else {
  echo 'Not Authenticated';
  prettyPrint($_SESSION);
}