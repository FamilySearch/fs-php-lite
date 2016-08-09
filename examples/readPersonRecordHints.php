<?php

include_once '_includes.php';
include_once '_header.php';

// Fetch the person's record hints if a person id has been provided
if ($_GET['pid']) {
  $response = $fs->get('/platform/tree/persons/' . $_GET['pid'] . '/matches?collection=records', [
    'headers' => [
      'Accept' => 'application/json'  
    ]  
  ]);
  echo '<h2>Read Person Record Hints Response</h2>';
  prettyPrint($response);
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

include_once '_footer.php';