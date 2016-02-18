Php Serial Modbus Class
======

This class is an implementation of the [ModBus RTU serial protocol][modbus] (for example via RS485 port) acting as ModBus master allowing easy-to-use ModBus communication.

Features
-------
  - Native support of function codes 01, 02, 03, 04, 05, 06
  - Additional function codes allowed by advanced methods
  - Simple single line method for communicating with slave
  - Timeout control
  - CRC check

Quick start example
------
```php
<?php
include("PhpSerialModbus.php");
$modbus = new PhpSerialModbus;
$modbus->deviceInit('/dev/ttyUSB0',115200,'none',8,1,'none');
$modbus->deviceOpen();
$result=$modbus->sendQuery(1,4,"310C",3);
print_r($result);
print "\nVoltage: ".(hexdec($result[0].$result[1])/100);
$modbus->deviceClose();
?>
```
This example will query the slave with id "1" asking the contents of Input Registers 310C, 310D and 310E and will produce the following output (on a Raspberry Pi connected via USB/RS485 converter to my Epever/Epsolar Tracer 2215BN MPPT solar charger controller):
```SH
Array
(
    [0] => 04
    [1] => be
    [2] => 00
    [3] => 44
    [4] => 03
    [5] => 39
)

Voltage: 12.14
```
The output is an array containing the bytes received by the slave in HEX format. Modbus registry datas are 16bit (8bit for Hi byte and 8bit for Lo byte) so the array count is 6. In order to convert this HEX data to readable format you have to convert the bytes received two by two. In the above example in order to get decimal contents of registry 310C, we have to concatenate the first two bytes received and we will obtain "04be", that in decimal is 1214 (the battery voltage * 100).


Class methods and properties
-------
**deviceInit** ($port='/dev/ttyUSB0', $baud=115200, $parity='none', $char=8, $sbits=1, $flow='none')
>Initialize the serial port with the given paramters

**deviceOpen** ()
>Open the serial port

**sendQuery** ($slaveId, $functionCode, $registerAddress, $regNumOrCount, $response = true)
>Send a query to the ModBus slave where $slaveid is the ID of the device, $functionCode is the ModBus functions code, $registerAddress is HEX address of the register we want to read/write, $regCountOrData is the count of register we want to read or the data we want to write and $getResponse (default is true) is a boolean to control if the datas have to return by the sendQuery() method or by the successive readResponse() method. The returned data is an array of HEX bytes without headers and without CRC. 

**readResponse** ($raw=false,$offsetl=0,$offsetr=0)
>Get response from serial port. Useful for some advanced use. You can send the query with the command sendQuery() and get response after a while. If $raw is set to true you will get "raw" binary response with headers and with CRC bytes, useful for advanded data manipulation. You can optionally insert a left offset ($offsetl) and a right offset ($offsetr) on the received data (only if not in "raw" mode.

**sendRawQuery** ($string, $response = true)
>This method permits to send a raw query without functions or CRC control. Very useful for advanced use and for non-standard Modbus functions codes. It sends the $strings to the serial device (You can use the format \x - For example the command sendQuery(1,4,"310C",3) is equivalent to sendRawQuery("\x01\x04\x31\x0c\x00\x03\x7e\xf4")

**crc16** ($data)
>This function return the binary CRC16 (ModBus) of $data. Userful with sendRawQuery or for additional CRC control

**$debug** = false
>Setting this property to TRUE will print HEX data sent and received through serial port. Useful for analyze raw data.

Note
------
If you use this class in HTTPD and not CLI don't forget to give the user the permssion to use serial port (for example with Apache on Debian: usermod -a -G dialout www-data)

Contributors
--------
This project is developed by Luca Soltoggio and is based on the class [PhpSerial] by Rémy Sanchez / Rizwan Kassim and partially inspired by Francesco Vettore's [blog][vettore].

http://arduinoelectronics.wordpress.com/ ~ http://minibianpi.wordpress.com

License
------
Copyright (C) 2016 Luca Soltoggio

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

[//]: #

   [phpserial]: <https://github.com/Xowap/PHP-Serial/>
   [vettore]: <http://blog.vettore.org/modbus-senza-paura/>
   [modbus]: http://modbustools.com/modbus.html