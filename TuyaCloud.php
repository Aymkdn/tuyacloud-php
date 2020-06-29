<?php
class TuyaCloud {
  var $profile = [];
  var $uri = '';
  var $tokens = '';
  var $accessToken = '';

  /**
   * Init the class with the profile information
   *
   * @param {Array} $profile
   *   @param {String} $userName The email used as username to access to SmartLife/Tuya app
   *   @param {String} $password The related password to access to SmartLife/Tuya app
   *   @param {String} $bizType Two possible values 'smart_life' or 'tuya'
   *   @param {String} $countryCode Country code (International dialing number), e.g. "33" for France or "1" for USA
   *   @param {String} $region The region code: "az" for Americas, "ay" for Asia, "eu" for Europe
   */
  function __construct($profile) {
    $this->profile = $profile;
    $this->profile['from'] = 'tuya';
    $this->uri = 'https://px1.tuya'.$profile["region"].'.com/homeassistant';
    // log in
    $this->login();
  }

  function post($uri, $body) {
    $ch = curl_init($uri);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json'
    ));
    $result = curl_exec($ch);
    return json_decode($result);
  }

  /**
   * It will login and get the tokens
   */
  function login() {
    $ch = curl_init($this->uri."/auth.do");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->profile));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/x-www-form-urlencoded'
    ));

    $data = curl_exec($ch);
    $this->tokens = json_decode($data);
    $this->accessToken = $this->tokens->access_token;
  }

  /**
   * Retrieve all the devices and scenario associated with the user
   *
   * @return {Array} Array of devices, e.g. [ {"data": {"online": true, "state": false}, "name": "Switch 1", "icon": "https://images.tuyaeu.com/smart/product_icon2/cz_1.png", "id": "4194aef6cb", "dev_type": "switch", "ha_type": "switch" }, …]
   */
  function getDevices() {
    // Scan network otherwise or no device id in options
    $body = [
      "header" => [
        "name" => 'Discovery',
        "namespace" => 'discovery',
        "payloadVersion" => 1
      ],
      "payload" => [
        "accessToken" => $this->accessToken
      ]
    ];
    $json = $this->post($this->uri.'/skill', $body);
    return $json->payload->devices;
  }

  /**
   * Set a state for a device
   *
   * @param {Object} $options
   *   @param {String} [$id] The device ID
   *   @param {String} [$name] The device Name: either the ID or the Name of the device must be provided
   *   @param {String} [$command="turnOnOff"] The command related to the value (see below)
   *   @param {Various} $value The value to apply (e.g. 1 to turn on, 0 to turn off)
   *
   * @return {Boolean} return TRUE if the operation was a success
   *
   * @example
   * The commands are (based on https://github.com/PaulAnnekov/tuyaha/blob/master/tuyaha/devices/):
   *   - All Devices:
   *     -> "turnOnOff" (value 0 === Off, value 1 === On)
   *   - Cover:
   *     -> "startStop" (value 0) to stop a cover
   *   - Climate:
   *     -> "temperatureSet"
   *     -> "windSpeedSet" (value is fan_mode)
   *     -> "modeSet" (value is operation_mode)
   *   - Fan:
   *     -> "windSpeedSet" (value is fan_mode)
   *   - Light:
   *     -> "brightnessSet"
   *     -> "colorSet" (value is hsv_color)
   *     -> "colorTemperatureSet" (value is color_temp)
   */
  function setState($options) {
    // by default we want to turn on or off
    if (!isset($options['command'])) $options['command']='turnOnOff';
    // transform "on" and true to 1
    // transform "off" and false to 0
    $value = $options['value'];
    if ($value === true || $value === 'on') $value=1;
    else if ($value === false || $value === 'off') $value=0;

    // if we have the device name, find the ID
    if (isset($options['name'])) {
      $options['name'] = strtolower($options['name']);
      $devices = $this->getDevices();
      foreach($devices as $device) {
        if (strtolower($device->name) === $options['name']) {
          $devId = $device->id;
          break;
        }
      }
    } else {
      $devId = $options['id'];
    }

    $payload = [
      "accessToken" => $this->accessToken,
      "devId" => $devId
    ];

    if ($options['command'] === "colorSet") {
      $payload["color"] = $value;
    } else {
      $payload["value"] = $value;
    }
    $json = $this->post($this->uri.'/skill', [
      "header" => [
        "name" => $options['command'],
        "namespace" => 'control',
        "payloadVersion" => 1
      ],
      "payload" => $payload
    ]);
    return $json->header->code === "SUCCESS";
  }

  /**
   * Get the state for a device
   *
   * @param   $option
   *   @param {String} [$id] The device ID
   *   @param {String} [$name] The device Name: either the ID or the Name of the device must be provided
   *
   * @return {Boolean|Number} TRUE (for "on"), FALSE (for "off"), or a value (e.g. a cover could return 3)
   */
  function getState($options) {
    $hasName = isset($options['name']);
    if ($hasName) $options['name'] = strtolower($options['name']);

    $devices = $this->getDevices();
    foreach($devices as $device) {
      if (($hasName && strtolower($device->name) === $options['name']) || (!$hasName && $device->id === $options['id'])) {
        return $device->data->state;
      }
    }
  }
}
?>
