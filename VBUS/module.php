<?php
	class VBUS extends IPSModule {

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			//$this->ConnectParent("{AC6C6E74-C797-40B3-BA82-F135D941D1A2}");
			$this->RegisterPropertyInteger("GatewayMode", 0);
			$this->RegisterPropertyInteger("VarName", 0);
			$this->RegisterPropertyString("Password", "vbus");
			//$this->RegisterPropertyString("DeviceName", "");
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
					break;
				case 1: //SerialPort bei Modus 1 erstellen
					$this->ForceParent("{6DC3D946-0D31-450F-A8C6-C42DB8D7D4F1}");
					$this->GetConfigurationForParent();
 					break;
			}
			$this->WriteAttributeString("DeviceName","");
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
			$this->SendDebug("Received", utf8_decode($data->Buffer) , 1);
			$payload = utf8_decode($data->Buffer);
			if (substr($payload,0,6) == "+HELLO")
			{
				$this->SendPass();
			}

			if($this->GetBuffer("IncommingBuffer") =="")
			{
				$this->SetBuffer("IncommingBuffer", $payload);
				$this->SendDebug("Buffer", $payload, 1);
				return;
			} else
			{
				$payload = $this->GetBuffer("IncommingBuffer") . $payload;
				$serchAApos = 0;
				$AA10pos = strpos($payload, "\xaa\x10\x00");
				if ($AA10pos !== false) 
				{
					$serchAApos = $AA10pos +1; // the trailing AA must higer than the leading 
				}
				$AA00pos = strpos($payload, "\xaa",$serchAApos);
				$this->SendDebug("SerchPos", "AA10: " . $AA10pos . " AA00: " . $AA00pos, 0);
				if ($AA10pos !== false && $AA00pos !== false && $AA10pos < $AA00pos)
				{
					$this->SetBuffer("IncommingBuffer",substr($payload,$AA00pos));
					$this->SendDebug("Buffer", substr($payload,$AA00pos), 0);
					$payload = substr($payload,$AA10pos,$AA00pos-$AA10pos); // cut from AA10 to the next AA
					$this->SendDebug("To Proceed", $payload, 1);
					$this->ProccedData($payload);
				} elseif ($AA10pos !== false && $AA00pos !== false && $AA10pos > $AA00pos)
				{
					$payload = substr($payload,$AA10pos);
					$this->SetBuffer("IncommingBuffer",$payload);
					$this->SendDebug("Buffer", $payload, 1);
				} else
				{
					$this->SetBuffer("IncommingBuffer",$payload);
					$this->SendDebug("Buffer", $payload, 1);
				}
			}
		}

		private function ProccedData($payload)
		{
			$language = $this->ReadPropertyInteger("VarName");
			if (substr($payload,0,2) == "\xaa\x10" && strlen($payload) >= 16) // it must have at least the header and one dataframe (16 bytes)
			{
				$payload = ltrim($payload , "\xaa\x10"); // remove the first 2 bytes, like the cutter
				define('NUMBER_OF_FRAMES', ord($payload{6}));
				define('HEADER_CHECKSUMME', ord($payload{7}));
				define('DEVICE_TYP', "0x" . dechex(ord($payload{2})) . dechex(ord($payload{1} )));
				define('XML_FILE', __DIR__ . "/../libs/VBusSpecificationResol.xml");
				$this->SendDebug("Device Typ",DEVICE_TYP,0);

				$cs = 16;       // durch den Cutter wird das erste Byte (0x10 Hex) abgeschnitten, hier wird der Wert wieder dazu genommen
				for ($i=00; $i<=06; $i++)
				{
					$cs += ord($payload{$i}); // add Headerbytes -> Checksumme 
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
					$septet = ord($payload{$i * 6 + 6});
						for ($j=00; $j<=03; $j++)
						{  // always 4 Bytes in a Frame
							$payload_byte = ord($payload{$i * 6 + 2 + $j});
							$byte_array[$k] = $payload_byte + 128 * (($septet >> $j) & 1); //das komplette Datenbyte aus dem Byte und dem Teil des Septet zusammenfügen
							$k++; // inc. Array Index 
							$cs += $payload_byte;// add payload to checksumm
						} // End payload Byte loop
						$cs += $septet; // add septet 
						$cs = $this->CalcCheckSumm($cs);
						// $this->SendDebug("Frame Checksumm","Frame $i >> Calculated: $cs , Received: ".ord($payload{$i * 6 + 7}),0);
						if ($cs != ord($payload{$i * 6 + 7})) // Checksumme Frame not ok?
						{
							$this->SendDebug("Frame Checksumm","Error in Frame $i >> calculated: $cs received: ".ord($payload{$i * 6 + 7}),0);
							return;
						}
					} // end for frame loop
					$this->SendDebug("Frame Checksumm","Checksumm OK for " . ($i - 1) . " frames",0);
				}
				else  // Checksumme Head not ok
				{
					$this->SendDebug("Header Checksumm","Error >> calculated: $cs received: ".ord($payload{7}),0);
					return;
				}	// end else
				// $this->SendDebug("Received Data",implode(" , ",$byte_array),0);
					
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
							$this->SendDebug("Device Name",DEVICE_TYP ." does not exist in the XML file",0);
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
								if (isset($field->name[$language]))
								{
									$field_name = (string)($field->name[$language]); // 0 = german 1 = english
								}
								else // EN description not available -> force to german
								{
									$field_name = (string)@($field->name[0]); 
								}
								$field_info = (string)$field['commonUsage'][0];
								$field_unit = (string)$field->unit;
								$field_bit_size = (int)$field->bitSize;
								$var_profil = "";
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
								if ((string)$field->format == "t") // Time
								{
									$var_value = mktime(0,$var_value,0);
									$var_profil = "~UnixTimestamp";
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
								$var_ident = DEVICE_TYP . $field_offset . (string)$field->bitPos;  // eindeutigen IDENT erzeugen
								switch ($var_type)
								{
									case 0: // bool
										$this->RegisterVariableBoolean($var_ident, $field_name, '~Switch', 0);
										if($this->GetValue($var_ident) != $var_value) 
										{
											SetValueBoolean($this->GetIDForIdent($var_ident), $var_value);
											$updatedvars += 1;
										}
									break;
									case 1: // integer
										$this->RegisterVariableInteger($var_ident, $field_name, $var_profil, 0);
										if($this->GetValue($var_ident) != $var_value) 
										{
											SetValueInteger($this->GetIDForIdent($var_ident), $var_value);
											$updatedvars += 1;
										}
									break;
									case 2: // float
										$this->RegisterVariableFloat($var_ident, $field_name, $var_profil, 0);
										if($this->GetValue($var_ident) != $var_value) 
										{
											SetValueFloat($this->GetIDForIdent($var_ident), $var_value);
											$updatedvars += 1;
										}
									break;
								} // end switch
							}
							$this->SendDebug("Success", $updatedvars . " Variables set",0);
							break; // break foreach no further devices needs to search
						} // end if
					} //end foreach
				} //end if
				else
				{
					$this->SendDebug("XML","Fail to load XML file",0);
				}
			}
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
				$data = "DATA" . CHR(13);
				$this->SendToLanAdapter($data);
			}
		}

		public function SendToLanAdapter(string $data)
		{
			$this->SendDataToParent(json_encode([
				'DataID' => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}",
				'Buffer' => utf8_encode($data),
			]));
		}

		private function CalcCheckSumm($cs)
		{
			$cs = ~$cs;	//invert Checksumm
			$cs &= 127;	//remove the MSB from Checksumm
			return $cs;
		}
	}