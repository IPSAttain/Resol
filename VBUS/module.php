<?php
//require_once(__DIR__ . "/../libs/VBusSpecificationResol.xml");  // Regler Spezifikationen
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
			$this->SendDebug("Received", utf8_decode($data->Buffer) , 1);
			$debug = true;
			$value = utf8_decode($data->Buffer);
			define('ANZAHL_FRAMES', ord($value{6}));
			define('HEADER_CHECKSUMME', ord($value{7}));
			define('REGLER_TYP', "0x" . dechex(ord($value{2})) . dechex(ord($value{1} )));
			define('SCRIPT_KENNUNG', 'V-Bus-Modul');
			define('XML_DATEI', 'VBusSpecificationResol.xml');
			if ($debug) $this->SendDebug(SCRIPT_KENNUNG,REGLER_TYP);

			$cs = 16;       // durch den Cutter wird das erste Byte (0x10 Hex) abgeschnitten, hier wird der Wert wieder dazu genommen
			for ($i=00; $i<=06; $i++)
			{
				$cs += ord($value{$i}); //Headerbytes zur Checksumme zusammenaddieren
			}
			$cs = ~$cs;	//Checksumme invertieren
			$cs &= 127;	//MSB aus Checksumme entfernen
			if ($debug) $this->SendDebug(SCRIPT_KENNUNG,"Berrechnete Checksumme Header: $cs , Empfangene Checksumme: " . HEADER_CHECKSUMME);
			if ( $cs == HEADER_CHECKSUMME)  // Checksumme ok?
			{
				if ($debug) $this->SendDebug(SCRIPT_KENNUNG,"HeaderChecksumme OK!");
				$byte_array = array();
				$k = 0; // array Index
				if ($debug) $this->SendDebug(SCRIPT_KENNUNG,"Anzahl der ermittelten Frames: " . ANZAHL_FRAMES);
				for ($i=01; $i<=ANZAHL_FRAMES; $i++) // Schleife für alle Datenframes
				{
				   $cs = 0;
				   $septet = ord($value{$i * 6 + 6});
					for ($j=00; $j<=03; $j++)
					{  // es sind immer 4 Bytes in einem Frame
						$payload_byte = ord($value{$i * 6 + 2 + $j});
						$byte_array[$k] = $payload_byte + 128 * (($septet >> $j) & 1); //das komplette Datenbyte aus dem Byte und dem Teil des Septet zusammenfügen
						$k++; //Array Index erhöhen
						$cs += $payload_byte;// Bytes zur Checksumme addieren
					} // End payload Byte Schleife
					$cs += $septet; // septet dazuaddieren
					$cs = ~$cs; //Checksumme invertieren
					$cs &= 127; //MSB aus Checksumme entfernen
					if ($debug) $this->SendDebug(SCRIPT_KENNUNG,"Berrechnete Checksumme Frame $i: $cs , Empfangene Checksumme: ".ord($value{$i * 6 + 7}));
					if ($cs != ord($value{$i * 6 + 7})) // Checksumme Frame not ok?
					{
						$this->SendDebug(SCRIPT_KENNUNG,"Checksummenfehler im Frame $i >> ermittelte Summe: $cs empfangene Summe: ".ord($value{$i * 6 + 7}));
						return;
					}
				} // end for frameschleife
			}
			else  // Checksumme Head not ok
			{
				$this->SendDebug(SCRIPT_KENNUNG,"Checksummenfehler Header >>Checksumme berrechnet: $cs Checksumme soll: ".ord($value{7}));
			}	// end else
			if ($debug) $this->SendDebug(SCRIPT_KENNUNG,print_r($byte_array));
				
		}

	}