<?php
/*
This example gives me the following output:

DEBUG [query sent]: \x01\x04\x31\x0c\x00\x03\x7e\xf4
DEBUG [response received]: \x01\x04\x06\x04\xb8\x00\x3d\x02\xe0\x50\x28
DEBUG [data]: \x04\xb8\x00\x3d\x02\xe0
Array
(
    [0] => 04
    [1] => b8
    [2] => 00
    [3] => 3d
    [4] => 02
    [5] => e0
)
DEBUG [query sent]: \x01\x04\x31\x0c\x00\x03\x7e\xf4
DEBUG [response received]: \x01\x04\x06\x04\xb7\x00\x49\x03\x71\x84\x0f
▒Iq▒
*/

// Include Php Serial Modbus Class
require 'PhpSerialModbus.php';

// Crate an instance of the class
$modbus = new PhpSerialModbus();

// Initialize port
$modbus->deviceInit('/dev/ttyUSB0',115200,'none',8,1,'none');

// Open port
$modbus->deviceOpen();

// Enable debug
$modbus->debug = true;

// Send query to slave 1 with function code 4, asking for 3 registers from 310C
$result=$modbus->sendQuery(1,4,"310C",3);
print_r($result);

// Send raw query
$rawquery="\x01\x04\x31\x0c\x00\x03";
$modbus->sendRawQuery($rawquery.$modbus->crc16($rawquery),false);

// Get response with raw data
$result=$modbus->getResponse(true);
print_r($result);

// Close device
$modbus->deviceClose();
?>