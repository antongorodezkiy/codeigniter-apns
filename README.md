Codeigniter-apns
(c) 2012, Anton Gorodezkiy

Это библиотека для codeigniter для взаимодействия с Apple Push Notifications Service
Библиотека основана на коде APNS Copyright (c) 2010 Benjamin Ortuzar Seconde <bortuzar@gmail.com>

Пример базового использования (controller):

	function send_notifications()
	{
		$this->load->library('apn');
		$this->apn->payloadMethod = 'enhance'; // включите этот метод для отладки
		$this->apn->connectToPush();

		// добавление собственных переменных в notification
		$this->apn->setData(array( 'someKey' => true ));

		$send_result = $this->apn->sendMessage($device_token, 'Тестовое уведомление #1 (TIME:'.date('H:i:s').')', /*badge*/ 2, /*sound*/ 'default'  );
			
		if($send_result)
			log_message('debug','Отправлено успешно');
		else
			log_message('error',$this->apn->error);
	
		
		$this->apn->disconnectPush();
	}
	
	// для получения идентификаторов устройств, на которых приложение больше не установлено
	public function apn_feedback()
	{
		$this->load->library('apn');

		$unactive = $this->apn->getFeedbackTokens();
		
		if (!count($unactive))
		{
			log_message('info','Feedback: No devices found. Stopping.');
			return false;
		}
		
		foreach($unactive as $u)
		{
			$devices_tokens[] = $u['devtoken'];
		}
	
		/*
		print_r($unactive) -> Array ( [0] => Array ( [timestamp] => 1340270617 [length] => 32 [devtoken] => 002bdf9985984f0b774e78f256eb6e6c6e5c576d3a0c8f1fd8ef9eb2c4499cb4 ) ) 
		*/
	}
	
--------------------------------------
	
Codeigniter-apns
(c) 2012, Anton Gorodezkiy

This is codeigniter library to work with Apple Push Notifications Service
It based on APNS Copyright (c) 2010 Benjamin Ortuzar Seconde <bortuzar@gmail.com>

Basic usage for pushing (controller):

	function send_notifications()
	{
		$this->load->library('apn');
		$this->apn->payloadMethod = 'enhance'; // you can turn on this method for debuggin purpose
		$this->apn->connectToPush();
		
		// adding custom variables to the notification
		$this->apn->setData(array( 'someKey' => true ));

		$send_result = $this->apn->sendMessage($device_token, 'Test notif #1 (TIME:'.date('H:i:s').')', /*badge*/ 2, /*sound*/ 'default'  );
			
		if($send_result)
			log_message('debug','Sending successful');
		else
			log_message('error',$this->apn->error);
	
		
		$this->apn->disconnectPush();
	}
	
	// designed for retreiving devices, on which app not installed anymore
	public function apn_feedback()
	{
		$this->load->library('apn');

		$unactive = $this->apn->getFeedbackTokens();
		
		if (!count($unactive))
		{
			log_message('info','Feedback: No devices found. Stopping.');
			return false;
		}
		
		foreach($unactive as $u)
		{
			$devices_tokens[] = $u['devtoken'];
		}
	
		/*
		print_r($unactive) -> Array ( [0] => Array ( [timestamp] => 1340270617 [length] => 32 [devtoken] => 002bdf9985984f0b774e78f256eb6e6c6e5c576d3a0c8f1fd8ef9eb2c4499cb4 ) ) 
		*/
	}
