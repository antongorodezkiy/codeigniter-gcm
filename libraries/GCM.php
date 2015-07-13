<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 *
 * @package GCM (Google Cloud Messaging)
 * @copyright (c) 2012 Anton Gorodezkiy
 * info: https://github.com/antongorodezkiy/codeigniter-gcm/
 * Description: PHP CodeIgniter Google Cloud Messaging Library
 * License: BSD
 *
 * Copyright (c) 2012, Anton Gorodezkiy
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

class GCM {

    protected $api_key = '';
    protected $api_send_address = '';
    protected $payload = array();
    protected $additional_data = array();
    protected $recepients = array();
    protected $message = '';
    public $status = array();
    public $messages_statuses = array();
    public $response_data = NULL;
    public $response_info = NULL;
    protected $error_statuses = array(
        'Unavailable' => 'Maybe missed API key',
        'MismatchSenderId' => 'Make sure you\'re using one of those when trying to send messages to the device. If you switch to a different sender, the existing registration IDs won\'t work.',
        'MissingRegistration' => 'Check that the request contains a registration ID',
        'InvalidRegistration' => 'Check the formatting of the registration ID that you pass to the server. Make sure it matches the registration ID the phone receives in the google',
        'NotRegistered' => 'Not registered',
        'MessageTooBig' => 'The total size of the payload data that is included in a message can\'t exceed 4096 bytes'
    );

    /**
     * Constructor
     */
    public function __construct()
	{
        $ci =& get_instance();
        $ci->load->config('gcm', true);
        $this->api_key = $ci->config->item('gcm_api_key', 'gcm');
        $this->api_send_address = $ci->config->item('gcm_api_send_address', 'gcm');
        if ( ! $this->api_key)
		{
            show_error('GCM: Needed API Key');
        }
        if ( ! $this->api_send_address)
		{
            show_error('GCM: Needed API Send Address');
        }
    }

    /**
    * Sets additional data which will be send with main apn message
    *
    * @param <array> $data
    * @return <array>
    */
    public function set_ttl($ttl = '')
    {
        if ( ! $ttl)
		{
			unset($this->payload['time_to_live']);
		}
        else
		{
			$this->payload['time_to_live'] = $ttl;
		}
    }

    /**
     * Setting GCM message
     *
     * @param <string> $message
     */
    public function set_message($message = '')
	{
        $this->message = $message;
        $this->payload['data']['message'] = $message;
    }

    /**
     * Setting data to message
     *
     * @param <string> $data
     */
    public function set_data($data = array())
	{
        $this->payload['data'] = $data;
        if ($this->message)
		{
			$this->payload['data']['message'] = $this->message;
		}
    }

    /**
     * Setting group of messages
     *
     * @param <string> $group
     */
    public function set_group($group = '')
	{
        if ( ! $group)
		{
			unset($this->payload['collapse_key']);
		}
        else
		{
			$this->payload['collapse_key'] = $group;
		}
    }

    /**
     * Adding one recipient
     *
     * @param <string> $group
     */
    public function add_recepient($registration_id)
	{
        $this->payload['registration_ids'][] = $registration_id;
    }

    /**
     * Setting all recipients
     *
     * @param <string> $group
     */
    public function set_recepients($registration_ids)
	{
        $this->payload['registration_ids'] = $registration_ids;
    }

    /**
     * Clearing group of messages
     */
    public function clear_recepients()
	{
        $this->payload['registration_ids'] = array();
    }

    /**
     * Sending messages to Google Cloud Messaging
     *
     * @param <string> $group
     */
    public function send()
    {
        $this->payload['registration_ids'] = array_unique($this->payload['registration_ids']);
        sort($this->payload['registration_ids']);
        if (isset($this->payload['time_to_live']) && ! isset($this->payload['collapse_key']))
		{
			$this->payload['collapse_key'] = 'GCM Notifications';
		}
        $data = json_encode($this->payload);
        return $this->request($data);
    }

    protected function request($data)
    {
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: key='.$this->api_key;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->api_send_address);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, FALSE);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $this->response_data = curl_exec($curl);
        $this->response_info = curl_getinfo($curl);
        curl_close($curl);
        return $this->parseResponse();
    }

    protected function parse_response()
    {
        if ($this->response_info['http_code'] == 200)
        {
            $response = explode("\n", $this->response_data);
            $response_body = json_decode($response[count($response) - 1]);
            if ($response_body->success && ! $response_body->failure)
            {
                $message = 'All messages were sent successfully';
                $error = FALSE;
            }
            elseif ($response_body->success && $response_body->failure)
            {
                $message = $response_body->success.' of '.($response_body->success+$response_body->failure).' messages were sent successfully';
                $error = TRUE;
            }
            elseif ( ! $response_body->success && $response_body->failure)
            {
                $message = 'No messages cannot be sent. '.$response_body->results[0]->error;
                $error = TRUE;
            }
            $this->status = array(
                'error' => $error,
                'message' => $message
            );
            $this->messages_statuses = array();
            foreach($response_body->results as $key => $result)
            {
                if (isset($result->error) && $result->error)
                {
                    $this->messages_statuses[$key] = array(
                        'error' => TRUE;
                        'regid' => $this->payload['registration_ids'][$key],
                        'message' => $this->errorStatuses[$result->error],
                        'message_id' => ''
                    );
                }
                else
                {
                    $this->messages_statuses[$key] = array(
                        'error' => FALSE;
                        'regid' => $this->payload['registration_ids'][$key],
                        'message' => 'Message was sent successfully',
                        'message_id' => $result->message_id
                    );
                }
            }
            return ! $error;
        }
        elseif ($this->response_info['http_code'] == 400)
        {
            $this->status = array(
                'error' => TRUE;
                'message' => 'Request could not be parsed as JSON'
            );
            return FALSE;
        }
        elseif ($this->response_info['http_code'] == 401)
        {
            $this->status = array(
                'error' => TRUE;
                'message' => 'There was an error authenticating the sender account'
            );
            return FALSE;
        }
        elseif ($this->response_info['http_code'] == 500)
        {
            $this->status = array(
                'error' => TRUE;
                'message' => 'There was an internal error in the GCM server while trying to process the request'
            );
            return FALSE;
        }
        elseif ($this->response_info['http_code'] == 503)
        {
            $this->status = array(
                'error' => TRUE;
                'message' => 'Server is temporarily unavailable'
            );
            return FALSE;
        }
        else
        {
            $this->status = array(
                'error' => TRUE;
                'message' => 'Status undefined'
            );
            return FALSE;
        }
    }
}
