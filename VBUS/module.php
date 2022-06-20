<?php
	class VBUS extends IPSModule {

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyInteger("Delay", 0);
			$this->RegisterPropertyInteger("GatewayMode", 0);
			$this->RegisterPropertyInteger("LanguageSelect", 0);
			$this->RegisterPropertyString("Password", "vbus");
			$this->RegisterTimer("Update", 0, "Resol_PassThru($this->InstanceID);");
			$this->RegisterTimer("TimeOut", 60000, "Resol_SendPass($this->InstanceID);");
			$this->RegisterAttributeBoolean("PassTrueBit",true);
			$this->RegisterAttributeString("DeviceName","");
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

			switch($this->ReadPropertyInteger("GatewayMode")) {
				case 0: //ClientSocket bei Modus 0 erstellen
					$this->ForceParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");
					$this->GetConfigurationForParent();
					$this->SendPass();
					$this->SetTimerInterval("TimeOut", 30000);
					break;
				case 1: //SerialPort bei Modus 1 erstellen
					$this->ForceParent("{6DC3D946-0D31-450F-A8C6-C42DB8D7D4F1}");
					$this->GetConfigurationForParent();
					$this->SetTimerInterval("TimeOut", 0);
 					break;
			}
			$this->WriteAttributeString("DeviceName","");
			$this->SetCyclicTimerInterval();
		}

		public function GetConfigurationForParent() {
			if ($this->ReadPropertyInteger("GatewayMode") == 1)
			{
				return "{\"BaudRate\": \"9600\", \"StopBits\": \"1\", \"DataBits\": \"8\", \"Parity\": \"None\"}";
			}
			if ($this->ReadPropertyInteger("GatewayMode") == 0)
			{
				return "{\"Port\": 7053}";
			}
		}

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			if ($this->ReadPropertyInteger("GatewayMode") == 0)
			{
				$this->SetTimerInterval("TimeOut", 30000);
			}
			elseif ($this->ReadPropertyInteger("GatewayMode") == 1 && $this->GetTimerInterval("TimeOut") != 0)
			{
				$this->SetTimerInterval("TimeOut", 0);
			}
			if (substr(utf8_decode($data->Buffer),0,6) == "+HELLO" )
			{
				$this->SendPass();
				$this->SendDebug("Received", utf8_decode($data->Buffer) , 0);
			} elseif (substr(utf8_decode($data->Buffer),0,3) == "+OK" )
			{
				// further development
				$this->SendDebug("Received", utf8_decode($data->Buffer) , 0);
			} else 
			{
				if ($this->ReadAttributeBoolean("PassTrueBit"))
				{
					$this->SendDebug(__FUNCTION__ . " Incomming", utf8_decode($data->Buffer) , 1);
					$payload = $this->GetBuffer("IncommingBuffer") . utf8_decode($data->Buffer);
					$this->SendDebug(__FUNCTION__ . " Current Payload", $payload , 1);
					$firstSyncByte = strpos($payload, "\xaa");
					$secondSyncByte = strpos($payload, "\xaa", $firstSyncByte +1);
					$this->SendDebug(__FUNCTION__ . " Sync Bytes", "First Byte Position: " . $firstSyncByte . " | Second Byte Position: " . $secondSyncByte, 0);
					$protocol = substr($payload, $firstSyncByte + 5 , 1);
					$this->SendDebug(__FUNCTION__ . " Read Protocol:", $protocol, 1);
					if ($secondSyncByte > $firstSyncByte) 
					{
						if ($protocol == "\x10") 
						{
							// proceed
							$payloaddata = substr($payload,$firstSyncByte,$secondSyncByte - $firstSyncByte);
							$this->SendDebug(__FUNCTION__ . " To Proceed", $payloaddata , 1);
							$this->ProccedData($payloaddata);
							$this->SetBuffer("IncommingBuffer",substr($payload,$secondSyncByte)); // put the rest back to the buffer
							$this->SendDebug(__FUNCTION__ . " Buffer Left", substr($payload,$secondSyncByte) , 1);
						}
						else
						{
							//cut all unwanted
							$this->SetBuffer("IncommingBuffer",substr($payload,$secondSyncByte));
							$this->SendDebug(__FUNCTION__ . "1 New Buffer", substr($payload,$secondSyncByte), 1);
							
						}
					}
					else 
					{
						$this->SetBuffer("IncommingBuffer",$payload);
						$this->SendDebug(__FUNCTION__ . "2 New Buffer", $payload, 1);
					}
				}
			}
		}

		private function ProccedData($payload)
		{
			$language = $this->ReadPropertyInteger("LanguageSelect");
			$payload = ltrim($payload , "\xaa\x10"); // remove the first 2 bytes, like the cutter
			define('NUMBER_OF_FRAMES', ord($payload[6]));
			define('HEADER_CHECKSUMME', ord($payload[7]));
			$device_typ = strtoupper(str_pad(dechex(ord($payload[2])), 2, "0", STR_PAD_LEFT) . str_pad(dechex(ord($payload[1])), 2, "0", STR_PAD_LEFT));
			define('DEVICE_TYP', "0x" .  $device_typ);
			define('XML_FILE', __DIR__ . "/../libs/VBusSpecificationResol.xml");
			$this->SendDebug("Device Typ",DEVICE_TYP,0);

			$cs = 16;       // durch den Cutter wird das erste Byte (0x10 Hex) abgeschnitten, hier wird der Wert wieder dazu genommen
			for ($i=00; $i<=06; $i++)
			{
				$cs += ord($payload[$i]); // add Headerbytes -> Checksumme 
			}
			$cs = $this->CalcCheckSumm($cs);
			$this->SendDebug("Header Checksumm","Calculated: $cs , Received: " . HEADER_CHECKSUMME,0);
			if ( $cs == HEADER_CHECKSUMME)  // Checksumm ok?
			{
				//$this->SendDebug("Header Checksumm","Checksumme OK!",0);
				$byte_array = array();
				$k = 0; // array Index
				$this->SendDebug("Frame Count","Number of Frames: " . (NUMBER_OF_FRAMES),0);
				if (strlen($payload) < (NUMBER_OF_FRAMES * 6 + 8)) // the length of the complete string must have the header (8 byte) and 6 byte for every data frame
				{
					$this->SendDebug("Payload","Lenght of Payload is to short. Calculated: " . (NUMBER_OF_FRAMES * 6 + 10) . "  Received: ". strlen($payload),0);
					return;
				}
				for ($i=01; $i<=NUMBER_OF_FRAMES; $i++) // loop for all frames
				{
				$cs = 0;
				$septet = ord($payload[$i * 6 + 6]);
					for ($j=00; $j<=03; $j++)
					{  // always 4 Bytes in a Frame
						$payload_byte = ord($payload[$i * 6 + 2 + $j]);
						$byte_array[$k] = $payload_byte + 128 * (($septet >> $j) & 1); //das komplette Datenbyte aus dem Byte und dem Teil des Septet zusammenfügen
						$k++; // inc. Array Index 
						$cs += $payload_byte;// add payload to checksumm
					} // End payload Byte loop
					$cs += $septet; // add septet 
					$cs = $this->CalcCheckSumm($cs);
					if ($cs != ord($payload[$i * 6 + 7])) // Checksumme Frame not ok?
					{
						$this->SendDebug("Frame Checksumm","Error in Frame $i >> calculated: $cs received: ".ord($payload[$i * 6 + 7]),0);
						return;
					}
				} // end for frame loop
				$this->SendDebug("Frame Checksumm","Checksumm OK for " . ($i - 1) . " frames",0);
			}
			else  // Checksumme Head not ok
			{
				$this->SendDebug("Header Checksumm","Error >> Calculated: $cs   Received: ".ord($payload[7]),0);
				return;
			}	// end else
	
			if (file_exists(XML_FILE))
			{
				$xml = simplexml_load_file(XML_FILE);	
				
				if($this->ReadAttributeString("DeviceName") == "")
				{
					### Look for the device in the xml file ###
					foreach($xml->device as $master)
					{
						if ($master->address == DEVICE_TYP)
						{
							$device_name = (string)$master->name;
							$this->SendDebug("Device Name",$device_name,0);
							$this->WriteAttributeString("DeviceName",$device_name);
							$this->UpdateFormField("DeviceName", "caption", $this->Translate('Found device ') . $device_name);
							break; // end foreach no further devices needs to search
						} // end if
					}
					if (!isset($device_name))
					{
						$this->SendDebug("Device Name: " . DEVICE_TYP ." does not exist in the XML file",0);
						$this->UpdateFormField("DeviceName", "caption", $this->Translate('Not supported device ') . DEVICE_TYP);
					}
				}
				### Regler
				foreach($xml->packet as $master)
				{
					if ($master->source == DEVICE_TYP) // match
					{
						$updatedvars = 0;
						foreach($master->field as $field)
						{
							$field_unit = "";
							$var_profil = "";
							$field_bit = "";
							$field_bit_size = 0;
							if (isset($field->name[$language]))
							{
								$field_name = (string)($field->name[$language]); // 0 = german 1 = english
							}
							else // EN description not available -> force to german
							{
								$field_name = (string)@($field->name[0]); 
							}
							//if (isset($field['commonUsage'][0]) $field_info = (string)@$field['commonUsage'][0];
							if (isset($field->unit)) $field_unit = (string)$field->unit; 
							if (isset($field->bitSize)) $field_bit_size = (int)$field->bitSize;
							if ($field_bit_size  == 1)
							{
								$var_type = 0; // 0 ^ bool
							}
							elseif ((float)$field->factor < 1 && (float)$field->factor > 0)
							{
								$var_type = 2; // ^ float
							}
							else // no factor or factor >= 1
							{
								$var_type = 1; // ^ integer
							}
							if (isset($field->field->offset)) // multiple sub values
							{
								$var_value = 0;
								foreach($field->field as $child_field)
								{
									$field_offset =   (int)$child_field->offset;
									$field_factor = (float)$child_field->factor;
									$var_value += ($byte_array[$field_offset] + 256 * $byte_array[$field_offset+1])* $field_factor;
								}
							}
							else // only on subvalue
							{
								$field_offset = (int)$field->offset;
								if (isset($field->factor))
								{
									$field_factor = (float)$field->factor;
								}
								else // no factor
								{
									$field_factor = 1;
								}
								switch ($field_bit_size)
								{
									case 32:
										$var_value  = $byte_array[$field_offset] + (2**8) * $byte_array[$field_offset+1] + (2**16) * $byte_array[$field_offset+2] + (2**24) * $byte_array[$field_offset+3];
										$var_value *= $field_factor;
									break;
									case 31:
										$var_value  = $byte_array[$field_offset] + (2**8) * $byte_array[$field_offset+1] + (2**16) * $byte_array[$field_offset+2] + (2**24) * $byte_array[$field_offset+3];
										$var_value -= ((2**32)*($var_value >> 31)); // if bit 31 == true , negative value
										$var_value *= $field_factor;
									break;
									case 16:
										$var_value  = $byte_array[$field_offset] + (2**8) * $byte_array[$field_offset+1];
										$var_value *= $field_factor;
									break;
									case 15:
										$var_value  = $byte_array[$field_offset] + 2**8 * $byte_array[$field_offset+1];
										$var_value -= ((2**16)*($var_value >> 15)); // if bit 15 == true , negative value
										$var_value *= $field_factor;
									break;
									case 8:
										$var_value = $byte_array[$field_offset];
									break;
									case 7:  
										$var_value = $byte_array[$field_offset];
										$var_value -= ((2**8)*($var_value >> 7)); // if bit 7 == true , negative value
									break;
									case 1:
										$field_bit = $field->bitPos;
										$var_value = (($byte_array[$field_offset] >> $field_bit) & 1);
										$var_profil = "~Switch";
									break;
								} // END Switch
							} //end else
							if (isset($field->format)) 
							{
								if ((string)$field->format == "t") // Time
								{
									$var_value = mktime(0,$var_value,0);
									$var_profil = "~UnixTimestamp";
								}
								}
							if ((string) $field_unit == " °C") {
								// Temperature
								$var_profil = "~Temperature";
							} elseif ($field_unit == " %") {
								// Pump Speed
								$var_profil = "~Intensity.100";
							}
							if ($var_profil == "" && $field_unit != "") 
							{
								$var_profil = $this->CreateVarProfil($field_bit_size, $field_unit, $var_type);
							}
							$var_ident = DEVICE_TYP . $field_offset . (string)$field_bit;  // eindeutigen IDENT erzeugen
							switch ($var_type)
							{
								case 0: // bool
									$this->RegisterVariableBoolean($var_ident, $field_name, '~Switch', 0);
									if($this->GetValue($var_ident) != $var_value) 
									{
										$this->SetValue($var_ident, $var_value);
										$updatedvars += 1;
									}
								break;
								case 1: // integer
									$this->RegisterVariableInteger($var_ident, $field_name, $var_profil, 0);
									if($this->GetValue($var_ident) != $var_value) 
									{
										$this->SetValue($var_ident, $var_value);
										$updatedvars += 1;
									}
								break;
								case 2: // float
									$this->RegisterVariableFloat($var_ident, $field_name, $var_profil, 0);
									if($this->GetValue($var_ident) != $var_value) 
									{
										$this->SetValue($var_ident, $var_value);
										$updatedvars += 1;
									}
								break;
							} // end switch
							//$this->SendDebug("Var Debug: ", $updatedvars ." Var | Field Name: ".$field_name. " | Var Type: " .$var_type . " | Var Profil: " . $var_profil,0);
						}
						$this->SendDebug("Success", $updatedvars . " Variables set",0);
						if($this->ReadPropertyInteger("Delay") != 0) $this->WriteAttributeBoolean("PassTrueBit",false);
						break; // break foreach no further devices needs to search
					} // end if
				} //end foreach
			} //end if
			else
			{
				$this->SendDebug("XML","Fail to load XML file",0);
			}
			return $var_profil;
		}

		private function CreateVarProfil($field_bit_size, $field_unit, $var_type)
		{
			$MaxValue = 2** (int)$field_bit_size;
			$var_profil = "Resol" . $field_unit;
			// keine Sonderzeichen im Var-Profilname zulässig
			$var_profil = preg_replace ( '/[^a-z0-9]/i', '_', $var_profil );
			if (!@IPS_GetVariableProfile($var_profil))
			{
				IPS_CreateVariableProfile($var_profil, $var_type);
				IPS_SetVariableProfileText($var_profil, "", $field_unit);
				IPS_SetVariableProfileIcon($var_profil,'Sun');
				IPS_SetVariableProfileValues ($var_profil, 0, $MaxValue, 1);
			}
			return $var_profil;
		}

		public function SendPass()
		{
			if($this->HasActiveParent())
			{
				$data =  "PASS " . $this->ReadPropertyString("Password") . CHR(13);
				$this->SendToLanAdapter($data);
				$this->SendDebug("Password", "Password: " . $this->ReadPropertyString("Password") . " send to LAN adapter" , 0);
				$data = "DATA" . CHR(13);
				$this->SendToLanAdapter($data);
			}
			else
			{
				$this->SendDebug("Password", "Can not send password: " . $this->ReadPropertyString("Password") . ". Client Socket not active" , 0);
			}
		}

		public function SendToLanAdapter(string $data)
		{
			$this->SendDataToParent(json_encode([
				'DataID' => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}",
				'Buffer' => utf8_encode($data),
			]));
		}

		protected function SetCyclicTimerInterval()
		{
			$seconds = $this->ReadPropertyInteger('Delay');
			$Interval = $seconds * 1000;
			$this->SetTimerInterval('Update', $Interval);
			$this->WriteAttributeBoolean("PassTrueBit",true);
		}

		public function PassThru()
		{
			$this->WriteAttributeBoolean("PassTrueBit",true);
			$this->SendDebug(__FUNCTION__, "Start Receiving Data" , 0);
		}

		protected function CalcCheckSumm($cs)
		{
			$cs = ~$cs;	//invert Checksumm
			$cs &= 127;	//remove the MSB from Checksumm
			return $cs;
		}
	}