<?php
	class VBUS extends IPSModule {

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			//$this->ConnectParent("{AC6C6E74-C797-40B3-BA82-F135D941D1A2}");
			$this->RegisterPropertyInteger("GatewayMode", 0);
			$this->RegisterPropertyString("Password", "Pass");
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

		public function SendPass()
		{
			$data =  "PASS " . $this->ReadPropertyString("Password") . CHR(13);
			$data .= "DATA" . CHR(13);
			$this->SendDataToParent(json_encode([
				'DataID' => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}",
				'Buffer' => utf8_encode($data),
			]));
		}

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			$language = 0;
			$this->SendDebug("Received", utf8_decode($data->Buffer) , 1);
			$payload = utf8_decode($data->Buffer);
			if (substr($payload,0,2) == "\xaa\x10") 
			{
				$value = ltrim(utf8_decode($data->Buffer), "\xaa\x10"); // remove the first 2 bytes, like the cutter
				define('ANZAHL_FRAMES', ord($value{6}));
				define('HEADER_CHECKSUMME', ord($value{7}));
				define('REGLER_TYP', "0x" . dechex(ord($value{2})) . dechex(ord($value{1} )));
				define('SCRIPT_KENNUNG', 'V-Bus-Modul');
				define('XML_DATEI', __DIR__ . "/../libs/VBusSpecificationResol.xml");
				$this->SendDebug("Regler Typ",REGLER_TYP,0);

				$cs = 16;       // durch den Cutter wird das erste Byte (0x10 Hex) abgeschnitten, hier wird der Wert wieder dazu genommen
				for ($i=00; $i<=06; $i++)
				{
					$cs += ord($value{$i}); //Headerbytes zur Checksumme zusammenaddieren
				}
				$cs = ~$cs;	//Checksumme invertieren
				$cs &= 127;	//MSB aus Checksumme entfernen
				$this->SendDebug("Header Checksumm","Calculated: $cs , Received: " . HEADER_CHECKSUMME,0);
				if ( $cs == HEADER_CHECKSUMME)  // Checksumme ok?
				{
					$this->SendDebug("Header Checksumm","Checksumme OK!",0);
					$byte_array = array();
					$k = 0; // array Index
					$this->SendDebug("Frame Count","Number of Frames: " . (ANZAHL_FRAMES+1),0);
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
						// $this->SendDebug("Frame Checksumm","Frame $i >> Calculated: $cs , Received: ".ord($value{$i * 6 + 7}),0);
						if ($cs != ord($value{$i * 6 + 7})) // Checksumme Frame not ok?
						{
							$this->SendDebug("Frame Checksumm","Error in Frame $i >> calculated: $cs received: ".ord($value{$i * 6 + 7}),0);
							return;
						}
					} // end for frameschleife
					$this->SendDebug("Frame Checksumm","Checksumm OK for $i frames",0);
				}
				else  // Checksumme Head not ok
				{
					$this->SendDebug("Header Checksumm","Error >> calculated: $cs received: ".ord($value{7}),0);
					return;
				}	// end else
				$this->SendDebug("Received Data",implode(" , ",$byte_array),0);
					
				if (file_exists(XML_DATEI))
				{
					$xml = simplexml_load_file(XML_DATEI);	
					
					### Regler Typ in der XML Datei suchen ###
					foreach($xml->device as $master)
					{
						if ($master->address == REGLER_TYP)
						{
							$regler_name = (string)$master->name;
							$this->SendDebug("Device Name",$regler_name,0);
							break; // end foreach
						} // end if
					}
					if (!isset($regler_name))
					{
						$this->SendDebug("Device Name",REGLER_TYP ." does not exist in the XML file",0);
					}
					### Regler
					foreach($xml->packet as $master)
					{
						if ($master->source == REGLER_TYP) // passenden Regler in der Datei gefunden
						{
							foreach($master->field as $field)
							{
								$field_name = (string)@($field->name[$language]); // 0 = deutsch 1 = englisch
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
								else // kein factor oder factor >= 1
								{
									$var_type = 1; // ^ integer
								}
								if (isset($field->field->offset)) // es gibt mehrere unterwerte
								{
									$var_value = 0;
									foreach($field->field as $child_field)
									{
										$field_offset =   (int)$child_field->offset;
										$field_factor = (float)$child_field->factor;
										$var_value += ($byte_array[$field_offset] + 256 * $byte_array[$field_offset+1])* $field_factor;
									}
								}
								else // nur 1 unterwert
								{
									$field_offset = (int)$field->offset;
									if (isset($field->factor))
									{
										$field_factor = (float)$field->factor;
									}
									else // wenn kein factor angegeben ist
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
											$var_value -= ((2**32)*($var_value >> 31)); // wenn bit 31 == true , Wert ist negativ
											$var_value *= $field_factor;
										break;
										case 16:
											$var_value  = $byte_array[$field_offset] + (2**8) * $byte_array[$field_offset+1];
											$var_value *= $field_factor;
										break;
										case 15:
											$var_value  = $byte_array[$field_offset] + 2**8 * $byte_array[$field_offset+1];
											$var_value -= ((2**16)*($var_value >> 15)); // wenn bit 15 == true , Wert ist negativ
											$var_value *= $field_factor;
										break;
										case 8:
											$var_value = $byte_array[$field_offset];
										break;
										case 7:  
											$var_value = $byte_array[$field_offset];
											$var_value -= ((2**8)*($var_value >> 7)); // wenn bit 7 == true , Wert ist negativ
										break;
										case 1:
											$field_bit = $field->bitPos;
											$var_value = (($byte_array[$field_offset] >> $field_bit) & 1);
											$var_profil = "~Switch";
										break;
									} // END Switch
								} //end else
								if ((string)$field->format == "t") // Systemzeit
								{
									$var_value = mktime(0,$var_value,0);
									$var_profil = "~UnixTimestamp";
								}
								if ((string) $field_unit == " °C") // Temperaturen
								{
									$var_profil = "~Temperature";
			
								}
								if ($field_unit == " %") // Drehzahlen
								{
									$var_profil = "~Intensity.100";
								}
								//if ($var_profil == "" && $field_unit != "") {$var_profil = ATN_CreateVariableProfile($var_type, $field_unit, $field_bit_size);}
								$var_ident = REGLER_TYP . $field_offset . (string)$field->bitPos;  // eindeutigen IDENT erzeugen
								$position = (int) $field_offset . (string)$field->bitPos;
								//$this->SendDebug("Field Output","Ident: " . $var_ident . "| Name: " . $field_name . "| Offset: " . $field_offset . "| Value: ".$var_value . " ".$field_unit . "| Profil: " .$var_profil ,0);
								switch ($var_type)
								{
									case 0: // bool
										$this->RegisterVariableBoolean($var_ident, $field_name, '~Switch', 0);
										if($this->GetValue($var_ident) != $var_value) SetValueBoolean($this->GetIDForIdent($var_ident), $var_value);
									break;
									case 1: // integer
										$this->RegisterVariableInteger($var_ident, $field_name, $var_profil, 0);
										if($this->GetValue($var_ident) != $var_value) SetValueInteger($this->GetIDForIdent($var_ident), $var_value);
									break;
									case 2: // float
										$this->RegisterVariableFloat($var_ident, $field_name, $var_profil, 0);
										if($this->GetValue($var_ident) != $var_value) SetValueFloat($this->GetIDForIdent($var_ident), $var_value);
									break;
								} // end switch
							}
						break; //foreach beenden
						} // end if
					} //end foreach
				} //end if
				else
				{
					$this->SendDebug("XML","Fail to load XML file",0);
				}
			}
		}
	}