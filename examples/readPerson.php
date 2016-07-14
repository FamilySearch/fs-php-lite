<?php

include_once '_includes.php';

// Fetch a person if a person id has been provided
if ($_GET['pid']) {
  $response = $fs->get('/platform/tree/persons/' . $_GET['pid']);
  prettyPrint($response->data);
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