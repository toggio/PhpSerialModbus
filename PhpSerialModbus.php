<?php
/*
 * PHP Serial Modbus Class (PhpSerialModbus) v0.9
 * 
 * THIS PROGRAM COMES WITH ABSOLUTELY NO WARRANTIES !
 * USE IT AT YOUR OWN RISKS !
 *
 * Copyright (C) 2016 under GPL v. 2 license
 * 17 February 2016
 *
 * @author Luca Soltoggio
 * http://www.arduinoelettronica.com/
 * https://arduinoelectronics.wordpress.com/
 *
 * This class is a easy-to-use implementation of the
 * ModBus RTU serial protocol (for example via RS485 port)
 * acting as ModBus Master with the support of 
 * function codes from 1 to 6.
 *
 * (take a look at http://www.modbustools.com/modbus.html
 * for more Modbus protocol infos)
 *
 */

/** Class was modified and upgaded 
* 13. August 2019.
* @author  Nikola Bencic
* Added:
*  - write verification for single holding reg write
*  - conversion from unsigned to signed 16bit int in order to use negative values ( like negative temperature )
* Changes:
* - set 2 stop bits by default to match Modbus standard ( devices work with one stop bit as well, but 2 are by Modbus standard specification )
**/
 
require_once 'PhpSerial.php';

class PhpSerialModbus
{
	// Enable for debugging
	public $debug = false;
	private $serial;
  
	public function __construct() 
	{
		$this->serial = new PhpSerial();
	}
 
	// Initialize serial port with specified parameters
	public function deviceInit($port='/dev/ttyUSB0', $baud=115200, $parity='none', $char=8, $sbits=1, $flow='none')
	{
		$this->serial->deviceSet($port);
		$this->serial->confBaudRate($baud);
		$this->serial->confParity($parity);
		$this->serial->confCharacterLength($char);
		$this->serial->confStopBits($sbits);
		$this->serial->confFlowControl($flow);
		exec('stty -F '.$port.' -brkint -icrnl -imaxbel -opost -isig -icanon -echo -echoe');
		return $this->serial->_dState; 
	}
  
	// Open serial port
	public function deviceOpen()
	{
		$this->serial->deviceOpen();
		return $this->serial->_dState;
	}
	
	// Close serial port
	public function deviceClose()
	{
		$this->serial->deviceClose();
		return $this->serial->_dState;
	}

	// Calculate CRC16 (ModBus)
	public function crc16($data)
	{
		$crc = 0xFFFF;
		for ($i = 0; $i < strlen($data); $i++)
		{
			$crc ^=ord($data[$i]);
     		for ($j = 8; $j !=0; $j--)
			{
				if (($crc & 0x0001) !=0)
				{
					$crc >>= 1;
					$crc ^= 0xA001;
				}
				else
				$crc >>= 1;
			}		
		}
		$highCrc=floor($crc/256);
		$lowCrc=($crc-$highCrc*256);
		return chr($lowCrc).chr($highCrc);
	}
	
	// Convert bin string to readable hex
	private function bin2hexString ($binString) 
	{
		$hexString=bin2hex($binString);
		$hexString=chunk_split($hexString,2,"\\x");
		$hexString= "\\x" . substr($hexString,0,-2);
		return $hexString;
	}
	

	private function Hex2String($hex){
	    $string='';
	    for ($i=0; $i < strlen($hex)-1; $i+=2){
	        $string .= chr(hexdec($hex[$i].$hex[$i+1]));
	    }
	    return $string;
	}

	//convert from uint16_t to int16_t or any other format
	public function convertResponse($input, $format = 'uint16_t'){

		$output = [];

		for($i = 0; $i < count($input); $i+=2)
		{

			$output[$i] = hexdec($input[$i].$input[($i+1)]);

			//conv. for 16-bit signed integer
				if($format == 'int16_t'){
					if($output[$i] > 32767) $output[$i] -= 65536;
				}
				else if($format == 'str')
				{
					$output[$i] = $this->Hex2String($input[$i].$input[($i+1)]);
				}
		}

		return array_values($output);
	}

	//send raw query
	public function sendRawQuery ($string, $response = true) {
		$this->serial->sendMessage($string);
		if ($this->debug) print "DEBUG [query sent]: ".$this->bin2hexString($string)."\n";
		if ($response) return $this->getResponse(); else return 1;		
	}
	
	// Send Modbus query to slave
	public function sendQuery ($slaveId, $functionCode, $registerAddress, $regCountOrData, $response = true, $validateWrite = false)
	{
		if ( ($functionCode > 6 ) || ($functionCode < 1) ) {
			if ($this->debug) print "DEBUG [invalid function code]\n";
			return 0;
		} 
		
		if ($functionCode < 5) $regCountOrData = dechex($regCountOrData);
				
		$regHighByte=hexdec(substr($registerAddress,0,2));
		$regLowByte=hexdec(substr($registerAddress,2));
		
		$regCountOrData = str_pad($regCountOrData,4,"0",STR_PAD_LEFT);
		
		$rcdHighByte=hexdec(substr($regCountOrData,0,2));
		$rcdLowByte=hexdec(substr($regCountOrData,2));
		 
		// Create query and convert to a binary string
		$query=array($slaveId, hexdec($functionCode), $regHighByte, $regLowByte, $rcdHighByte, $rcdLowByte);
		$queryString=implode(array_map("chr",$query));
	
		// Calculate CRC
		$queryString.=$this->crc16($queryString);
	
		if ($this->debug) print "DEBUG [query sent]: ".$this->bin2hexString($queryString)."\n";
		
		// Send over serial port
		$this->serial->sendMessage($queryString);

		// Modbus slave returns command on successful write
		if ($validateWrite) return $this->getResponse(false, 0, 0, $this->bin2hexString($queryString));
		
		if ($response) return $this->getResponse(); else return 1;
	}
	
	// Read response from slave
	public function getResponse ($raw=false,$offsetl=0,$offsetr=0, $sentQuery=null)
	{
		// Time started (for timing)
		$startTime = microtime(true);
		
		$responseString = '';
		
		// Allow until one second for the respone
		while(((microtime(true)-$startTime)<1) && ($responseString=='') ) 
		{
			// Read serial port buffer (with three seconds timeout) 
			while( ($byte = $this->serial->ReadPort()) && ((microtime(true)-$startTime)<3.0)) {
				$responseString=$responseString.$byte;
				usleep(50);
			}
		}
		
		if ($this->debug) print "DEBUG [response received]: ".$this->bin2hexString($responseString)."\n";

		//validate write command by comparing response from modbus slave, if response is equal to sent query from master -> write was successful
		if ($this->debug && $sentQuery !== null) print "DEBUG [write verification will be done against sent query]: ".$sentQuery."\n";
		if ($sentQuery !== null)
		{
			if ($sentQuery == $this->bin2hexString($responseString))
			{
				if ($this->debug) print "DEBUG [ sent - received -> MATCH!] \n";
				return true; 
			}
			else
			{
				if ($this->debug) print "DEBUG [ sent - received -> NO MATCH!] \n";
				return false;
			}
		}
		
		if ($raw) return $responseString;
		
		$stringLength=strlen($responseString);
	
		// If we have at least 5 bytes...
		if ($stringLength>=5)
		{	 
			// ...but no more than 5
			if ($stringLength==5) {
				if ($this->debug) print "DEBUG [no valid data]\n";
				return 0;
			}
			
			// CRC Check
			$checkArray=(str_split($responseString,strlen($responseString)-2));	
			$crc=$this->crc16($checkArray[0]);
			if ($crc!=$checkArray[1]) {
				if ($this->debug) print "DEBUG [crc check failed]: (expected ".$this->bin2hexString($crc)." received ".$this->bin2hexString($checkArray[1]).")\n";
				return 0;
			}
			
			// Convert string in array of bytes
			$responseArray = str_split($responseString);
			// This is the byte containing the numbere of data bytes
			$bytesNum = hexdec(bin2hex($responseArray[2]));
						
			// Create a new array with data without headers and CRC
			$responseData = array_slice($responseArray,3+$offsetl,$bytesNum-$offsetr);
			
			if ($this->debug) print "DEBUG [data]: ".$this->bin2hexString(implode($responseData))."\n";
			
			// Return an array with hex data bytes
			return array_map("bin2hex",$responseData);
		} else 
			{
			if ($this->debug) print "DEBUG [no response]\n";
			return 0;
			}		
		return 0; 
	}
} 
?>
