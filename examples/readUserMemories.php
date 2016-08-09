<?php

include_once '_includes.php';
include_once '_header.php';

// First we get the current user which includes a url to the memories endpoint
// for the user.
$userResponse = $fs->get('/platform/users/current');

if ($userResponse->data) {
  
  $user = $userResponse->data['users'][0];
  
  $memoriesResponse = $fs->get($user['links']['artifacts']['href']);
  
  echo '<h2>Read User Memories Response</h2>';
  prettyPrint($memoriesResponse);
  
} else {
  
  echo '<h3>Error reading the current user.<h3>';
  
  prettyPrint($userResponse);
  
}

include_once '_footer.php';