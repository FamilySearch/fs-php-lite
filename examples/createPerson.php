<?php

include_once '_includes.php';
include_once '_header.php';

// Fetch a person if a person id has been provided
if ($_POST['person']) {
  echo '<h2>Create Person Response</h2>';
  $response = $fs->post('/platform/tree/persons', [
    'body' => [
        'persons' => [
            json_decode($_POST['person'])
        ]    
    ]
  ]);
  prettyPrint($response);
}

// Show a form if a person id hasn't been provided
else {
  ?>
    <form method="POST">
      <label>Person Data:</label>
      <textarea name="person">{
    "living" : false,
    "gender" : {
      "type" : "http://gedcomx.org/Male"
    },
    "names" : [ {
      "type" : "http://gedcomx.org/BirthName",
      "nameForms" : [ {
        "fullText" : "Ebenezer Clark",
        "parts" : [ {
          "type" : "http://gedcomx.org/Given",
          "value" : "Ebenezer"
        }, {
          "type" : "http://gedcomx.org/Surname",
          "value" : "Clark"
        } ]
      } ],
      "preferred" : true
    } ],
    "facts" : [ {
      "type" : "http://gedcomx.org/Birth",
      "date" : {
        "original" : "29 Nov 1651",
        "formal" : "+1651-11-29"
      },
      "place" : {
        "original" : "NEW HAVEN,NEW HAVEN,CONN"
      }
    }, {
      "type" : "http://gedcomx.org/Death",
      "date" : {
        "original" : "1721",
        "formal" : "+1721"
      },
      "place" : {
        "original" : "WALLINGFORD,NEW HAVEN CONN."
      }
    }, {
      "type" : "http://gedcomx.org/Burial",
      "date" : {
        "original" : "1721",
        "formal" : "+1721"
      },
      "place" : {
        "original" : "WALLINGFORD,NEW HAVEN CONN."
      }
    }, {
      "type" : "http://gedcomx.org/Christening",
      "date" : {
        "original" : "29 Nov 1651",
        "formal" : "+1651-11-29"
      },
      "place" : {
        "original" : "NEW HAVEN,NEW HAVEN,CONN"
      }
    } ]
}
</textarea>
      <button>Submit</button>
    </form>
    <style>
        textarea {
            display: block;
            width: 100%;
            height: 300px;
            margin-bottom: 1em;
        }
    </style>
  <?php
}

include_once '_footer.php';