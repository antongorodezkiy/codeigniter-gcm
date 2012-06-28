<?php 
/**
 *
 * @package GCM (Google Cloud Messaging)
 * @copyright (c) 2012 AntonGorodezkiy
 * info: https://github.com/antongorodezkiy/codeigniter-gcm/
 * Description: PHP Codeigniter Google Cloud Messaging Library
 * License: GNU/GPL 2
 */

class GCM {
	
	protected $apiKey = '';
	protected $apiSendAddress = '';
	protected $payload = array();
	protected $additionalData = array();
	protected $recepients = array();
	protected $message = '';
	
	public $status = array();
	public $responseData = null;
	public $responseInfo = null;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		
		$ci =& get_instance();
		$ci->load->config('gcm',true);
		
		$this->apiKey = $ci->config->item('gcm_api_key','gcm');
		$this->apiSendAddress = $ci->config->item('gcm_api_send_address','gcm');
		
		if (!$this->apiKey) {
			show_error('GCM: Needed API Key');
		}
		
		if (!$this->apiSendAddress) {
			show_error('GCM: Needed API Send Address');
		}
	}
	
	
	/**
	* Sets additional data which will be send with main apn message
	*
	* @param <array> $data
	* @return <array>
	*/
	public function setTtl($ttl = '')
	{
		if (!$ttl)
			unset($this->payload['time_to_live']);
		else
			$this->payload['time_to_live'] = $ttl;
	}
	
	
	/**
	 * Setting GCM message
	 *
	 * @param <string> $message
	 */
	public function setMessage($message = '') {
		
		$this->message = $message;
		$this->payload['data']['message'] = $message;

	}
	
	
	/**
	 * Setting data to message
	 *
	 * @param <string> $data
	 */
	public function setData($data = array()) {

		$this->payload['data'] = $data;
		
		if ($this->message)
			$this->payload['data']['message'] = $this->message;
		
	}
	
	
	/**
	 * Setting group of messages
	 *
	 * @param <string> $group
	 */
	public function setGroup($group = '') {
		
		if (!$group)
			unset($this->payload['collapse_key']);
		else
			$this->payload['collapse_key'] = $group;
	}
	
	
	/**
	 * Setting group of messages
	 *
	 * @param <string> $group
	 */
	public function addRecepient($registrationId) {
		
		$this->payload['registration_ids'][] = $registrationId;
	}
	
	
	/**
	 * Senging messages to Google Cloud Messaging
	 *
	 * @param <string> $group
	 */
	public function send()
	{
		$this->payload['registration_ids'] = array_unique($this->payload['registration_ids']);
		
		if (isset($this->payload['time_to_live']) && !isset($this->payload['collapse_key']))
			$this->payload['collapse_key'] = 'Punchmo Notifications';
		
		$data = json_encode($this->payload);
		return $this->request($data);
	}
	
	
	
	
	
	
	
	protected function request($data)
	{

		$headers[] = 'Content-Type:application/json';
		$headers[] = 'Authorization:auth='.$this->apiKey;
		
		$curl = curl_init();
		  
		curl_setopt($curl, CURLOPT_URL, $this->apiSendAddress);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

		$this->responseData = curl_exec($curl);

		$this->responseInfo = curl_getinfo($curl);
		
		curl_close($curl);

		

		return $this->parseResponse();
	}
	
	
	protected function parseResponse()
	{
		if ($this->responseInfo['http_code'] == 200)
		{
			
			// Filter message-id or error from response
			if (preg_match("/id=([a-z0-9_%:\-]+)/i", $response_data, $matches) == 1) {
				return $matches[1];
			} else if (preg_match("/Error=([a-z0-9_\-]+)/i", $response_data, $matches) == 1) {
				throw new Exception($matches[1]);
			}
			
			$status = array(
				'error' => 0,
				'message' => 'Message was processed successfully'
			);
		}
		elseif ($this->responseInfo['http_code'] == 400)
		{
			$status = array(
				'error' => 1,
				'message' => 'Request could not be parsed as JSON'
			);
			return false;
		}
		elseif ($this->responseInfo['http_code'] == 401)
		{
			$status = array(
				'error' => 1,
				'message' => 'There was an error authenticating the sender account'
			);
			return false;
		}
		elseif ($this->responseInfo['http_code'] == 500)
		{
			$status = array(
				'error' => 1,
				'message' => 'There was an internal error in the GCM server while trying to process the request'
			);
			return false;
		}
		elseif ($response_info['http_code'] == 503)
		{
			$status = array(
				'error' => 1,
				'message' => 'Server is temporarily unavailable'
			);
			return false;
		}
		else
		{
			$status = array(
				'error' => 1,
				'message' => 'Status undefined'
			);
			return false;
		}
	}
	
}

