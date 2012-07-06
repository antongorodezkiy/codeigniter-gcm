codeigniter-gcm
===============

Codeigniter library for working with Google Cloud Messaging

Based on code of c2dm package, (c) 2011 lytsing.org & 2012 thebub.net


Simple example of usage:


	// controller
	public function send_gcm()
	{
		$this->load->library('gcm');
		
		$this->gcm->setMessage('Punchmo Test message '.date('d.m.Y H:s:i'));
		$this->gcm->addRecepient('RegistrationId');
		$this->gcm->addRecepient('New reg id');
		
		if ($this->gcm->send())
			echo 'Success for all messages';
		else
			echo 'Some messages have errors';

		print_r($this->gcm->status);
		print_r($this->gcm->messagesStatuses);
			
		die(' Worked.');
	}
