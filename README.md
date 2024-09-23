# tuyacloud-php

PHP Library to access to the SmartLife / Tuya objects using the Tuya Cloud API.

Note: you need a Smartlife Account created with an email, not with Google Connection or similar third party. You could also create a new account from email and then invite this account to the "family" of your main Smartlife Account.

## Required

A developer account must be created on the [Tuya platform](https://eu.platform.tuya.com/). I'm not sure how the details, but from the menu, access to [`Cloud` > `Development`](https://eu.platform.tuya.com/cloud/) and create a cloud project.

Once the project is created, it will show you the **access key** and **access secret**.

Under the tab `devices`, you can tie the project with your Tuya/SmartLife app by scanning a QR Code. If it worked, you will see all your devices listed on this page (with their `device_id`).

You need to know which region/server you're using, and then you have to use the correct URL:
 - China Data Center: https://openapi.tuyacn.com
 - Western America Data Center: https://openapi.tuyaus.com
 - Central Europe Data Center: https://openapi.tuyaeu.com
 - India Data Center: https://openapi.tuyain.com


## Usage

```php
<?php
require 'TuyaCloud.php';

$options = [
  'baseUrl' => 'https://openapi.tuyaeu.com', // URL API of Tuya
  'accessKey' => 'nhepe4mrrtz8wju45mk3', // access key of your app
  'secretKey' => 'sf94ryyrfvg3awvg4174m88wjpksytre', // access secret of your app
];

$client = new TuyaCloud($options);
try {
  // to get the device status
  // you must pass the device_id
  $response = $client->getDevice('bfa18afnfyre87eb7ne0');
  echo '<pre>';
  print_r($response);
  echo '</pre>';
  
  // to send a command 
  // you can pass a JSON string: '{"commands":[{"code":"switch_led","value":true}]}'
  // or a strClass object
  // or an array like the below one:
  $commands = [
    "commands" => [
      [
        "code" => "switch_led",
        "value" => true
      ]
    ]
  ];
  $response = $client->setDevice('bfa18afnfyre87eb7ne0', $commands);
} catch (Exception $e) {
  echo 'Error: ' . $e->getMessage();
}
?>
```
