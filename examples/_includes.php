<?php

session_start();

include '../src/FamilySearch.php';

$fs = new FamilySearch([
  'environment' => 'sandbox',
  'appKey' => 'a02j000000CBv4gAAD',
  'redirectUri' => 'https://fs-php-lite-justincy.c9users.io/examples/oauthResponse.php',
]);

/**
 * Pretty print a PHP variable
 * 
 * @param mixed $var
 */
function prettyPrint($var){
  echo '<pre>', print_r($var, true), '</pre>';
}