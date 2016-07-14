<?php

// Read the default portrait for a PID.

include_once '_includes.php';

// Fetch a person if a person id has been provided
if ($_GET['pid']) {
  
  $personResponse = $fs->get('/platform/tree/persons/' . $_GET['pid']);
  
  if ($personResponse->data) {
    
    // Extract the person from the person response object
    $person = $personResponse->data['persons'][0];
    
    // Issue a GET request to the person's portrait link
    $portraitResponse = $fs->get($person['links']['portrait']['href']);
    
    prettyPrint($portraitResponse);
  } else {
    echo '<h3>Error reading person ' . $_GET['pid'] . '</h3>';
    prettyPrint($personResponse);
  }
  
}

// Show a form if a person id hasn't been provided
else {
  ?>
    <form>
      <label>Person ID:</label>
      <input type="text" name="pid" placeholder="KWMX-PR9" />
      <button>Submit</button>
    </form>
  <?php
}