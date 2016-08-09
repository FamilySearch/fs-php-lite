<?php

// Create and attach a source to a person

include_once '_includes.php';
include_once '_header.php';

// Fetch a person if a person id has been provided
if ($_GET['pid'] && $_GET['title']) {
  
  echo 'In progress...';
  
  // Create a source description
  $sourceDescription = [
    'titles' => [
      [ 
        'value' => $_GET['title']  
      ]
    ]
  ];
  if ($_GET['url']) {
    $sourceDescription['about'] = $_GET['url'];
  }
  if ($_GET['citation']) {
    $sourceDescription['citations'] = [
      [
        'value' => $_GET['citation']
      ]
    ];
  }
  if ($_GET['notes']) {
    $sourceDescription['notes'] = [
      [
        'value' => $_GET['notes']
      ]
    ];
  }
  $createSourceResponse = $fs->post('/platform/sources/descriptions', [
    'body' => [
      'sourceDescriptions' => [
        $sourceDescription
      ]
    ]
  ]);
  
  if ($createSourceResponse->statusCode === 201) {
  
    echo '<h2>Create Source Response</h2>';
    prettyPrint($createSourceResponse);
  
    // Attach the source to the person
    $attachUrl = '/platform/tree/persons/' . $_GET['pid'] . '/source-references';
    $attachResponse = $fs->post($attachUrl, [
      'body' => [
        'persons' => [
          [
            'sources' => [
              [
                'description' => $createSourceResponse->headers['Location']
              ]
            ]
          ]  
        ]
      ]
    ]);
    
    echo '<h2>Attach Source Response</h2>';
    prettyPrint($attachResponse);
    
  } else {
    
    echo '<h3>Something unexpected occurred while creating the source.</h3>';
    prettyPrint($createSourceResponse);
    
  }
  
}

// Show a form if a person id hasn't been provided
else {
  ?>
    <form>
      <div>
        <label>Person ID:</label>
        <input type="text" name="pid" placeholder="KWMX-PR9" />
      </div>
      <div>
        <label>Source Title:</label>
        <input type="text" name="title" placeholder="Source Title" />
      </div>
      <div>
        <label>Source URL:</label>
        <input type="text" name="url" placeholder="Source URL" />
      </div>
      <div>
        <label>Source Citation:</label>
        <textarea name="citation" placeholder="Source Citation"></textarea>
      </div>
      <div>
        <label>Source Notes:</label>
        <textarea name="notes" placeholder="Source Notes"></textarea>
      </div>
      <button>Submit</button>
    </form>
    <style>
      form > div {
        margin-bottom: .5em;
        width: 300px;
      }
      
      label {
        display: block;
        font-weight: bold;
      }
      
      input, textarea {
        width: 100%;
      }
      
      textarea {
        height: 65px;
      }
    </style>
  <?php
}

include_once '_footer.php';