<?php

session_start();

include '../src/FamilySearch.php';

$fs = new FamilySearch([
  'environment' => 'sandbox',
  'appKey' => 'a02j000000CBv4gAAD',
  'redirectUri' => calculateBaseUrl() . '/examples/oauthResponse.php',
]);

/**
 * Pretty print a PHP variable
 * 
 * @param mixed $var
 */
function prettyPrint($var){
  echo '<pre>', print_r($var, true), '</pre>';
}

/**
 * Calculate the apps protocol and domain. This allows us to run the app both
 * locally and in Heroku without having to modify the redirect URI.
 * 
 * @return string
 */
function calculateBaseUrl(){
  return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?: $_SERVER['REQUEST_SCHEME']) . '://' . $_SERVER['HTTP_HOST'];
}