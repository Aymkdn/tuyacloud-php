<?php
class TuyaCloud {
  private $baseUrl;
  private $accessKey;
  private $secretKey;
  private $tokenStore;

  public function __construct($options) {
    $this->baseUrl = $options['baseUrl'];
    $this->accessKey = $options['accessKey'];
    $this->secretKey = $options['secretKey'];
  }

  // Function to sign the request
  private function signRequest($method, $path, $timestamp, $accessToken = '', $body = '') {
    $ctxHash = hash_init('sha256');
    hash_update($ctxHash, $body);
    $contentHash = bin2hex(hash_final($ctxHash, true));

    // build the stringToSign
    $stringToSign = strtoupper($method) . "\n" . $contentHash . "\n\n" . urldecode($path);

    // build signStr with accessKey, accessToken and timestamp
    $signStr = $this->accessKey . $accessToken . $timestamp . $stringToSign;

    return strtoupper(hash_hmac('sha256', $signStr, $this->secretKey));
  }

  public function getAccessToken() {
    $timestamp = round(microtime(true) * 1000);

    // check if we have a valid token in memory
    $SHM_KEY = ftok(__FILE__, chr(1));
    $keyStore = crc32("tuyacloud_token");
    $store = shm_attach($SHM_KEY);
    if (shm_has_var($store, $keyStore)) {
      $dataStore = shm_get_var($store, $keyStore);
      $dataStore = json_decode($dataStore);
      if ($dataStore->expire > $timestamp) {
        return $dataStore->access_token;
      }
    }

    // if not, we retrieve it
    $path = '/v1.0/token?grant_type=1'; // URL to get the acces token

    $signature = $this->signRequest('GET', $path, $timestamp);

    // En-têtes de requête
    $headers = [
      'client_id: ' . $this->accessKey,
      'sign: ' . $signature,
      't: ' . $timestamp,
      'sign_method: HMAC-SHA256'
    ];

    // send the request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if (isset($data['result']['access_token'])) {
      // save token
      $dataStore = new stdClass();
      $dataStore->access_token = $data['result']['access_token'];
      $dataStore->expire = $timestamp+($data['result']['expire_time']*1000);
      if (!shm_put_var($store, $keyStore, json_encode($dataStore))) throw new Exception("[tuyacloud] 'shm_put_var' failed to store the access_token");
      return $data['result']['access_token'];
    }

    throw new Exception('[tuyacloud] Unable to retrieve access token');
  }

  private function sendRequest($path, $method, $data = "{}") {
    // $data can be a JSON string, an Array or an Object
    // so we test
    // check the JSON string is valid
    if (is_string($data)) {
      json_decode($data);
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException("[tuyacloud] The argument must be a valid JSON string, an array, or a stdClass object.");
      }
    }    
    // encode the array to a JSON string
    else if (is_array($data)) {
      $data = json_encode($data);
    }    
    // if it's a stdClass object, convert it to a JSON string
    else if (is_object($data) && $data instanceof stdClass) {
      $data = json_encode($data);
    }

    $timestamp = round(microtime(true) * 1000);
    $token = $this->getAccessToken();
    $sign = $this->signRequest($method, $path, $timestamp, $token, $data);

    $url = $this->baseUrl . $path;
    $headers = [
      'Accept: application/json, text/plain, */*',
      't: ' . $timestamp,
      'sign: ' . $sign,
      'client_id: ' . $this->accessKey,
      'sign_method: HMAC-SHA256',
      'access_token: ' . $token,
      'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
  }

  /**
   * Get the device status/properties (`switch_led`, `bright_value`, `fan_switch`, …)
   * Documentation: https://developer.tuya.com/en/docs/cloud/1ef1a3044b
   *
   * @param  {String} $deviceId The device id
   * @return {Object}           {success:(boolean), result:[{code, value}]}
   */
  public function getDevice($deviceId) {
    if (!isset($deviceId)) throw "[tuyacloud] You have to pass the `device_id` as an argument to this function 'getDevice'.";
    return $this->sendRequest('/v1.0/iot-03/devices/' . $deviceId. '/status', 'GET');
  }

  /**
   * Set the device properties (`switch_led`, `bright_value`, `fan_switch`, …)
   * Documentation: https://developer.tuya.com/en/docs/cloud/e2512fb901
   *
   * @param  {String} $deviceId The device id
   * @param  {String|Object|Array} $commands An array of commands (e.g. `[{"code":"switch_led", "value":false}, {"code":"fan_switch", "value":true}`)
   * @return {Object}           {success:(boolean), result:[{code, value}]}
   */
  public function setDevice($deviceId, $commands) {
    if (func_num_args() != 2) throw "[tuyacloud] You have to pass the `device_id` and the `commands` as arguments to this function 'setDevice'.";
    return $this->sendRequest('/v1.0/iot-03/devices/' . $deviceId. '/commands', 'POST', $commands);
  }
}
?>
