<?php

/*!
 * ua-parser-php v0.1
 *
 * Copyright (c) 2011-2012 Dave Olsen, http://dmolsen.com
 * Licensed under the MIT license
 *
 * ua-parser-php is a PHP-based pseudo-port of the ua-parser project. Learn more about the ua-parser project at:
 * 
 * http://code.google.com/p/ua-parser/
 *
 * The user agents data from the ua-parser project is licensed under the Apache license.
 * spyc-0.5, for loading the YAML, is licensed under the MIT license.
 *
 */

// load spyc as a YAML loader
require(__DIR__."/lib/spyc-0.5/spyc.php");

class UA {
	
	private static $ua;
	private static $accept;
	private static $regexes;
	
	private static $debug = false;
	
	/**
	* Sets up some standard variables as well as starts the user agent parsing process
	*
	* @return {Object}       the result of the user agent parsing
	*/
	public function parse() {
		
		self::$ua      = $_SERVER["HTTP_USER_AGENT"];
		self::$accept  = $_SERVER["HTTP_ACCEPT"];
		self::$regexes = Spyc::YAMLLoad(__DIR__."/resources/user_agents_regex.yaml");
		
		// run the regexes to match things up
		$uaRegexes = self::$regexes['user_agent_parsers'];
		foreach ($uaRegexes as $uaRegex) {
			if ($result = self::uaParser($uaRegex)) {
				$result->uaOriginal = self::$ua;
				break;
			}
		}
		
		// if no browser was found check to see if it can be matched at least against a device (e.g. spider, generic feature phone or generic smartphone)
		if (!$result) {
			$result = self::deviceParser();
		}
		
		// still false?! see if it's a really dumb feature phone
		if (!$result) {
			if ((strpos(self::$accept,'text/vnd.wap.wml') > 0) || (strpos(self::$accept,'application/vnd.wap.xhtml+xml') > 0) || isset($_SERVER['HTTP_X_WAP_PROFILE']) || isset($_SERVER['HTTP_PROFILE'])) {
				$result = new stdClass();
				$result->device     = "Generic Feature Phone";
				$result->deviceFull = "Generic Feature Phone";
			}
		}

		// log the results when testing
		if (self::$debug) {
			self::log($result);
		}
		
		return $result;
	}
	
	/**
	* Attemps to see if the user agent matches the regex for this test. If so it populates an obj
	* with properties based on the user agent. Will also try to fetch OS & device properties
	*
	* @param  {Array}        the regex to be tested as well as any extra variables that need to be swapped
	*
	* @return {Object}       the result of the user agent parsing
	*/
	private function uaParser($regex) {
		
		// tests the supplied regex against the user agent
		if (preg_match("/".str_replace("/","\/",$regex['regex'])."/",self::$ua,$matches)) {
			
			// build the obj that will be returned
			$obj = new stdClass();
			
			// build the version numbers for the browser
			$obj->major  = $regex['v1_replacement'] ? $regex['v1_replacement'] : $matches[2];
			if (isset($matches[3]) || isset($regex['v2_replacement'])) {
				$obj->minor = isset($regex['v2_replacement']) ? $regex['v2_replacement'] : $matches[3];
			}
			if (isset($matches[4])) {
				$obj->build = $matches[4];
			}
			if (isset($matches[5])) {
				$obj->revision = $matches[5];
			}
			
			// pull out the browser family. replace the version number if necessary
			$obj->browser = $regex['family_replacement'] ? str_replace("$1",$obj->major,$regex['family_replacement']) : $matches[1];
			
			// set-up a clean version number
			$obj->version = isset($obj->major) ? $obj->major : "";
			$obj->version = isset($obj->minor) ? $obj->version.'.'.$obj->minor : $obj->version;
			$obj->version = isset($obj->build) ? $obj->version.'.'.$obj->build : $obj->version;
			$obj->version = isset($obj->revision) ? $obj->version.'.'.$obj->revision : $obj->version;
			
			// prettify
			$obj->browserFull = $obj->browser;
			if ($obj->version != '') {
				$obj->browserFull .= " ".$obj->version;
			}
			
			// figure out the OS for the browser, if possible
			if ($osObj = self::osParser()) {
				$obj = (object) array_merge((array) $obj, (array) $osObj);
			}
			
			// figure out the device name for the browser, if possible
			if ($deviceObj = self::deviceParser()) {
				$obj = (object) array_merge((array) $obj, (array) $deviceObj);
			}
			
			// create an attribute combinining browser and os
			if ($obj->osFull) {
				$obj->full = $obj->browserFull."/".$obj->osFull;
			}
			
			// detect if this is a uiwebview call on iOS
			if (($obj->browser == 'Mobile Safari') && !strstr(self::$ua,'Safari')) {
				$obj->uiwebview = true;
			} else {
				$obj->uiwebview = false;
			}
			
			// detect if this is a tablet
			if ((strstr($obj->device, 'Kindle')) || ($obj->device == 'iPad') || (($obj->os == 'Android') && !strstr(self::$ua, 'Mobile'))) {
				$obj->tablet = true;
			} else {
				$obj->tablet = false;
			}
			
			return $obj;
			
		} else {
			return false;
		}
	}
	
	/**
	* If the user agent is matched in uaParser() it also tries to check the OS and get properties
	*
	* @return {Object}       the result of the os parsing
	*/
	private function osParser() {
		
		// build the obj that will be returned
		$osObj = new stdClass();
		
		// run the regexes to match things up
		$osRegexes = self::$regexes['os_parsers'];
		foreach ($osRegexes as $osRegex) {
			if (preg_match("/".str_replace("/","\/",$osRegex['regex'])."/",self::$ua,$matches)) {
				
				// basic properties
				$osObj->osMajor  = $osRegex['os_v1_replacement'] ? $osRegex['os_v1_replacement'] : $matches[2];
				$osObj->osMinor  = $osRegex['os_v2_replacement'] ? $osRegex['os_v2_replacement'] : $matches[3];
				if (isset($matches[4])) {
					$osObj->osBuild = $matches[4];
				}
				if (isset($matches[5])) {
					$osObj->osRevision = $matches[5];
				}
				$osObj->osMinor  = $osRegex['os_v2_replacement'] ? $osRegex['os_v2_replacement'] : $matches[3];
				$osObj->os       = $osRegex['os_replacement']    ? str_replace("$1",$osObj->osMajor,$osRegex['os_replacement'])  : $matches[1];
				
				// os version
				$osObj->osVersion = isset($osObj->osMajor) ? $osObj->osMajor : "";
				$osObj->osVersion = isset($osObj->osMinor) ? $osObj->osVersion.'.'.$osObj->osMinor : $osObj->osVersion;
				$osObj->osVersion = isset($osObj->osBuild) ? $osObj->osVersion.'.'.$osObj->osBuild : $osObj->osVersion;
				$osObj->osVersion = isset($osObj->osRevision) ? $osObj->osVersion.'.'.$osObj->osRevision : $osObj->osVersion; 
				
				// prettify
				$osObj->osFull = $osObj->os." ".$osObj->osVersion;
				
				return $osObj;
			}
		}
		return false;
	}
	
	/**
	* If the user agent is matched in uaParser() it also tries to check the device and get its properties
	*
	* @return {Object}       the result of the device parsing
	*/
	private function deviceParser() {
		
		// build the obj that will be returned
		$deviceObj = new stdClass();
		
		// run the regexes to match things up
		$deviceRegexes = self::$regexes['device_parsers'];
		foreach ($deviceRegexes as $deviceRegex) {
			if (preg_match("/".str_replace("/","\/",$deviceRegex['regex'])."/i",self::$ua,$matches)) {
				
				// basic properties
				$deviceObj->deviceMajor  = $deviceRegex['device_v1_replacement'] ? $deviceRegex['device_v1_replacement'] : $matches[2];
				$deviceObj->deviceMinor  = $deviceRegex['device_v2_replacement'] ? $deviceRegex['device_v2_replacement'] : $matches[3];
				$deviceObj->device       = $deviceRegex['device_replacement'] ? str_replace("$1",$matches[1],$deviceRegex['device_replacement']) : $matches[1];
				
				// device version?
				$deviceObj->deviceVersion = isset($deviceObj->deviceMajor) ? $deviceObj->deviceMajor : "";
				$deviceObj->deviceVersion = isset($deviceObj->deviceMinor) ? $deviceObj->deviceVersion.'.'.$deviceObj->deviceMinor : $deviceObj->deviceVersion;
				
				// prettify
				$deviceObj->deviceFull = $deviceObj->device." ".$deviceObj->deviceVersion;
				
				return $deviceObj;
			}
		}
		return false;
	}
	
	/**
	* Logs the user agent info
	*/
	private function log($data) {
		if (!$data) {
			$data = new stdClass();
			$data->ua = self::$ua;
		} 
		$jsonData = json_encode($data);
		$fp = fopen(__DIR__."/log/user_agents.log", "a");
		fwrite($fp, $jsonData."\r\n");
		fclose($fp);
	}
	
	/**
	* Gets the latest user agent. Back-ups the old version first. it will fail silently if something is wrong...
	*/
	public function get() {
		if ($data = @file_get_contents("http://ua-parser.googlecode.com/svn/trunk/resources/user_agent_parser.yaml")) {
			copy(__DIR__."/resources/user_agents_regex.yaml", __DIR__."/resources/user_agents_regex.".date("Ymdhis").".yaml");
			$fp = fopen(__DIR__."/resources/user_agents_regex.yaml", "w");
			fwrite($fp, $data);
			fclose($fp);
		}
	}
}