<?php
	class VBUS extends IPSModule {

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->ConnectParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
		}

		public function Send(string $Text)
		{
			$this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $Text)));
		}

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			IPS_LogMessage("Device RECV", utf8_decode($data->Buffer));
		}

	}