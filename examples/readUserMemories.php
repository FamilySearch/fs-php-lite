<?php

include_once '_includes.php';

// First we get the current user which includes a url to the memories endpoint
// for the user.
$userResponse = $fs->get('/platform/users/current');

if ($userResponse->data) {
  
  $user = $userResponse->data['users'][0];
  
  $memoriesResponse = $fs->get($user['links']['artifacts']['href']);
  
  prettyPrint($memoriesResponse);
  
} else {
  
  echo '<h3>Error reading the current user.<h3>';
  
  prettyPrint($userResponse);
  
}