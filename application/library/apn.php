<?php
# -*- coding: utf-8 -*-
##
##     Copyright (c) 2010 Benjamin Ortuzar Seconde <bortuzar@gmail.com>
##
##     This file is part of APNS.
##
##     APNS is free software: you can redistribute it and/or modify
##     it under the terms of the GNU Lesser General Public License as
##     published by the Free Software Foundation, either version 3 of
##     the License, or (at your option) any later version.
##
##     APNS is distributed in the hope that it will be useful,
##     but WITHOUT ANY WARRANTY; without even the implied warranty of
##     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
##     GNU General Public License for more details.
##
##     You should have received a copy of the GNU General Public License
##     along with APNS.  If not, see <http://www.gnu.org/licenses/>.
##
##
## $Id: Apns.php 168 2010-08-28 01:24:04Z Benjamin Ortuzar Seconde $
##
#######################################################################
/*
 * Modified and adapted for Codeigniter
 * (c) Anton_Gorodezkiy, antongorodezkiy@gmail.com
 * 2012
*/

/**
 * Apple Push Notification Server
 */
class APN
{

/*******************************
	PROTECTED : */

	
	
	protected $server;
	protected $keyCertFilePath;
	protected $passphrase;
	protected $pushStream;
	protected $feedbackStream;
	protected $timeout;
	protected $idCounter = 0;
	protected $expiry;
	protected $allowReconnect = true;
	protected $additionalData = array();
	protected $apnResonses = array(
		0 => 'No errors encountered',
		1 => 'Processing error',
		2 => 'Missing device token',
		3 => 'Missing topic',
		4 => 'Missing payload',
		5 => 'Invalid token size',
		6 => 'Invalid topic size',
		7 => 'Invalid payload size',
		8 => 'Invalid token',
		255 => 'None (unknown)',
	);
	
	private $connection_start;
	
	public $error;
	public $payloadMethod = 'simple';
	
	/**
	* Connects to the server with the certificate and passphrase
	*
	* @return <void>
	*/
	protected function connect($server) {

		$ctx = stream_context_create();
		stream_context_set_option($ctx, 'ssl', 'local_cert', $this->keyCertFilePath);
		stream_context_set_option($ctx, 'ssl', 'passphrase', $this->passphrase);

		$stream = stream_socket_client($server, $err, $errstr, $this->timeout, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
		log_message('debug',"APN: Maybe some errors: $err: $errstr");
		
		
		if (!$stream) {
			
			if ($err)
				show_error("APN Failed to connect: $err $errstr");
			else
				show_error("APN Failed to connect: Something wrong with context");
				
			return false;
		}
		else {
			stream_set_timeout($stream,20);
			log_message('debug',"APN: Opening connection to: {$server}");
			return $stream;
		}
	}
	
	
	
	/**
	* Generates the payload
	* 
	* @param <string> $message
	* @param <int> $badge
	* @param <string> $sound
	* @return <string>
	*/
	protected function generatePayload($message, $badge = NULL, $sound = NULL, $newstand = false) {

	   $body = array();

	   // additional data
			if (is_array($this->additionalData) && count($this->additionalData))
			{
				$body = $this->additionalData;
			}

		//message
			$body['aps'] = array('alert' => $message);

		//badge
			if ($badge)
				$body['aps']['badge'] = $badge;
			
			if ($badge == 'clear')
				$body['aps']['badge'] = 0;

		 //sound
			if ($sound)
				$body['aps']['sound'] = $sound;

		//newstand content-available
			if($newstand)
				$body['aps']['content-available'] = 1;
				

	   $payload = json_encode($body);
	   log_message('debug',"APN: generatePayload '$payload'");
	   return $payload;
	}
	
	
	
	/**
	 * Writes the contents of payload to the file stream
	 * 
	 * @param <string> $deviceToken
	 * @param <string> $payload
	 */
	protected function sendPayloadSimple($deviceToken, $payload){

		$this->idCounter++;		

		log_message('debug',"APN: sendPayloadSimple to '$deviceToken'");

		$msg = chr(0) 									// command
			. pack('n',32)									// token length
			. pack('H*', $deviceToken)						// device token
			. pack('n',strlen($payload))					// payload length
			. $payload;										// payload
		
		log_message('debug',"APN: payload: '$msg'");
		log_message('debug',"APN: payload length: '".strlen($msg)."'");
		$result = fwrite($this->pushStream, $msg, strlen($msg));
		
		if ($result)
			return true;
		else
			return false;
	}
	
	
	/**
	 * Writes the contents of payload to the file stream with enhanced api (expiry, debug)
	 * 
	 * @param <string> $deviceToken
	 * @param <string> $payload
	 */
	protected function sendPayloadEnhance($deviceToken, $payload, $expiry = 86400) {
		
		if (!is_resource($this->pushStream))
			$this->reconnectPush();
		
		
		$this->idCounter++;		

		log_message('debug',"APN: sendPayloadEnhance to '$deviceToken'");

		$msg = chr(1)										// command
			. pack("N",time())								// identifier
			. pack("N",time() + $expiry)					// expiry
			. pack('n',32)									// token length
			. pack('H*', $deviceToken)						// device token
			. pack('n',strlen($payload))					// payload length
			. $payload;
			
		$response = @unpack('Ccommand/Nidentifier/Nexpiry/ntoken_length/H*device_token/npayload_length', $msg);// payload
		
		log_message('debug',"APN: unpack: '".print_r($response,true)."'");
		log_message('debug',"APN: payload: '$msg'");
		log_message('debug',"APN: payload length: '".strlen($msg)."'");
		$result = fwrite($this->pushStream, $msg, strlen($msg));
		
		if ($result)
		{
			return $this->getPayloadStatuses();
		}
	
		return false;
	}
	
	
	protected function timeoutSoon($left_seconds = 5)
	{
		$t = ( (round(microtime(true) - $this->connection_start) >= ($this->timeout - $left_seconds)));
		return (bool)$t;
	}
	
	
	
/* 	PROTECTED ^ 
*******************************/

        
	/**
	 * Connects to the APNS server with a certificate and a passphrase
	 *
	 * @param <string> $server
	 * @param <string> $keyCertFilePath
	 * @param <string> $passphrase
	 */
	function __construct() {
		
		$this->_ci = get_instance();
		
		$this->_ci->config->load('apn',true);

		
		if(!file_exists($this->_ci->config->item('PermissionFile','apn')))
		{
			show_error("APN Failed to connect: APN Permission file not found");
		}
		
		$this->pushServer = $this->_ci->config->item('Sandbox','apn') ? $this->_ci->config->item('PushGatewaySandbox','apn') : $this->_ci->config->item('PushGateway','apn');
		$this->feedbackServer = $this->_ci->config->item('Sandbox','apn') ? $this->_ci->config->item('FeedbackGatewaySandbox','apn') : $this->_ci->config->item('FeedbackGateway','apn');
		
		$this->keyCertFilePath = $this->_ci->config->item('PermissionFile','apn');
		$this->passphrase = $this->_ci->config->item('PassPhrase','apn');
		$this->timeout = $this->_ci->config->item('Timeout','apn') ? $this->_ci->config->item('Timeout','apn') : 60;
		$this->expiry = $this->_ci->config->item('Expiry','apn') ? $this->_ci->config->item('Expiry','apn') : 86400;
	}

	
        
	
	/**
	 * Public connector to push service
	 */
	public function connectToPush()
	{
		if (!$this->pushStream or !is_resource($this->pushStream))
		{
			log_message('debug',"APN: connectToPush");
		
			$this->pushStream = $this->connect($this->pushServer);
			
			if ($this->pushStream)
			{
				$this->connection_start = microtime(true);
				//stream_set_blocking($this->pushStream,0);
			}
		}
		
		return $this->pushStream;
	}
	
	/**
	 * Public connector to feedback service
	 */
	public function connectToFeedback()
	{
		log_message('info',"APN: connectToFeedback");
		return $this->feedbackStream = $this->connect($this->feedbackServer);
	}
	
	/**
	 * Public diconnector to push service
	 */
	function disconnectPush()
	{
		log_message('debug',"APN: disconnectPush");
		if ($this->pushStream && is_resource($this->pushStream))
		{
			$this->connection_start = 0;
			return @fclose($this->pushStream);
		}
		else
			return true;
	}
	
	/**
	 * Public disconnector to feedback service
	 */
	function disconnectFeedback()
	{
		log_message('info',"APN: disconnectFeedback");
		if ($this->feedbackStream && is_resource($this->feedbackStream))
			return @fclose($this->feedbackStream);
		else
			return true;
	}
	
	function reconnectPush()
	{
		$this->disconnectPush();
				
		if ($this->connectToPush())
		{
			log_message('debug',"APN: reconnect");
			return true;
		}
		else
		{
			log_message('debug',"APN: cannot reconnect");
			return false;
		}
	}
	
	function tryReconnectPush()
	{
		if ($this->allowReconnect)
		{
			if($this->timeoutSoon())
			{
				return $this->reconnectPush();
			}
		}
		
		return false;
	}
	
        
	/**
	 * Sends a message to device
	 * 
	 * @param <string> $deviceToken
	 * @param <string> $message
	 * @param <int> $badge
	 * @param <string> $sound
	 */
	public function sendMessage($deviceToken, $message, $badge = NULL, $sound = NULL, $expiry = '', $newstand = false)
	{
		$this->error = '';
		
		if (!ctype_xdigit($deviceToken))
		{
			log_message('debug',"APN: Error - '$deviceToken' token is invalid. Provided device token contains not hexadecimal chars");
			$this->error = 'Invalid device token. Provided device token contains not hexadecimal chars';
			return false;
		}
		
		// restart the connection
		$this->tryReconnectPush();
		
		log_message('info',"APN: sendMessage '$message' to $deviceToken");
		
		//generate the payload
		$payload = $this->generatePayload($message, $badge, $sound, $newstand);

		$deviceToken = str_replace(' ', '', $deviceToken);
		
		//send payload to the device.
		if ($this->payloadMethod == 'simple')
			$this->sendPayloadSimple($deviceToken, $payload);
		else
		{
			if (!$expiry)
				$expiry = $this->expiry;
			
			return $this->sendPayloadEnhance($deviceToken, $payload, $expiry);
		}
	}


	/**
	 * Writes the contents of payload to the file stream
	 * 
	 * @param <string> $deviceToken
	 * @param <string> $payload
	 * @return <bool> 
	 */
	function getPayloadStatuses()
	{
		
		$read = array($this->pushStream);
		$null = null;
		$changedStreams = stream_select($read, $null, $null, 0, 2000000);

		if ($changedStreams === false)
		{    
			log_message('error',"APN Error: Unabled to wait for a stream availability");
		}
		elseif ($changedStreams > 0)
		{
			
			$responseBinary = fread($this->pushStream, 6);
			if ($responseBinary !== false || strlen($responseBinary) == 6) {
				
				if (!$responseBinary)
					return true;
				
				$response = @unpack('Ccommand/Cstatus_code/Nidentifier', $responseBinary);
				
				log_message('debug','APN: debugPayload response - '.print_r($response,true));
				
				if ($response && $response['status_code'] > 0)
				{
					log_message('error','APN: debugPayload response - status_code:'.$response['status_code'].' => '.$this->apnResonses[$response['status_code']]);
					$this->error = $this->apnResonses[$response['status_code']];
					return false;
				}
				else
				{
					if (isset($response['status_code']))
						log_message('debug','APN: debugPayload response - '.print_r($response['status_code'],true));
				}
				
			}
			else
			{
				log_message('debug',"APN: responseBinary = $responseBinary");
				return false;
			}
		}
		else
			log_message('debug',"APN: No streams to change, $changedStreams");
		
		return true;
	}



	/**
	* Gets an array of feedback tokens
	*
	* @return <array>
	*/
	public function getFeedbackTokens() {
	    
		log_message('debug',"APN: getFeedbackTokens {$this->feedbackStream}");
		$this->connectToFeedback();
		
	    $feedback_tokens = array();
	    //and read the data on the connection:
	    while(!feof($this->feedbackStream)) {
	        $data = fread($this->feedbackStream, 38);
	        if(strlen($data)) {	   
	        	//echo $data;     	
	            $feedback_tokens[] = unpack("N1timestamp/n1length/H*devtoken", $data);
	        }
	    }
		
		$this->disconnectFeedback();
		
	    return $feedback_tokens;
	}

	
	/**
	* Sets additional data which will be send with main apn message
	*
	* @param <array> $data
	* @return <array>
	*/
	public function setData($data)
	{
		if (!is_array($data))
		{
			log_message('error',"APN: cannot add additional data - not an array");
			return false;
		}
		
		if (isset($data['apn']))
		{
			log_message('error',"APN: cannot add additional data - key 'apn' is reserved");
			return false;
		}
		
		return $this->additionalData = $data;
	}
	


	/**
	* Closes the stream
	*/
	function __destruct(){
		$this->disconnectPush();
		$this->disconnectFeedback();
	}

}//end of class


