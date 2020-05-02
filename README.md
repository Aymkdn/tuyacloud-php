# tuyacloud-php

PHP Library to access to the SmartLife / Tuya objects using the Cloud of Home Assistant.

It's based on https://github.com/unparagoned/cloudtuya

## Usage

```php
<?php
require 'TuyaCloud.php';

$tuya = new TuyaCloud([
  "userName" => "email@domain.com", // username/email to access to SmartLife/Tuya app
  "password" => "MyPassword", // password to access to SmartLife/Tuya app
  "bizType" => "smart_life", // type ('tuya' or 'smart_life')
  "countryCode" => "33", // Country code (International dialing number), e.g. "33" for France or "1" for USA
  "region" => "eu" // region (az=Americas, ay=Asia, eu=Europe)
]);

// to get a list of your devices
$devices = $tuya->getDevices();
foreach($devices as $device) {
  echo "Name: ".$device->name."<br>";
  echo "ID: ".$device->id."<br>";
  echo "Type: ".$device->dev_type."<br>";
  if ($device->dev_type !== "scene") {
    echo "State: ".$device->data->state."<br>";
    echo "Online: ".$device->data->online."<br>";
  }
}

// to switch on a device called "switch 1"
$tuya->setState([
  "name" => "switch 1",
  "value" => "on"
]);

// to switch off a device with ID "123456"
$tuya->setState([
  "id" => "123456",
  "value" => "off"
]);

// to stop a cover
$tuya->setState([
  "name" => "cover living room",
  "command" => "startStop",
  "value" => 0
]);

// to get the state of a device
echo "State Swtich 1 => ".$tuya->setState([
  "name" => "switch 1"
]);
?>
```
