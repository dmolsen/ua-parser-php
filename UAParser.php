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
	private static $regexes;
	
	public function parse() {
		
		self::$ua      = $_SERVER["HTTP_USER_AGENT"];
		self::$regexes = Spyc::YAMLLoad(__DIR__."/resources/user_agents_regex.yaml");
		
		foreach (self::$regexes['user_agent_parsers'] as $regex) {
			if ($result = self::uaParser($regex)) {
				$result->uaOriginal = self::$ua;
				break;
			}
		}
		
		return $result;
	}
	
	private function uaParser($regex) {
		
		if (preg_match("/".str_replace("/","\/",$regex['regex'])."/",self::$ua,$matches)) {
			
			// build the obj that will be returned
			$obj = new stdClass();
			
			// build the version numbers for the browser
			$obj->major  = $regex['v1_replacement'] ? $regex['v1_replacement'] : $matches[2];
			if (isset($matches[3])) {
				$obj->minor = $matches[3];
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
			$obj->browserFull = $obj->browser." ".$obj->version;
			
			// figure out the OS for the browser, if possible
			if ($osObj = self::osParser()) {
				$obj = (object) array_merge((array) $obj, (array) $osObj);
			}
			
			// figure out the device name for the browser, if possible
			if ($deviceObj = self::deviceParser()) {
				$obj = (object) array_merge((array) $obj, (array) $deviceObj);
			}
			
			if ($obj->osFull) {
				$obj->full = $obj->browserFull."/".$obj->osFull;
			}
			
			return $obj;
			
		} else {
			return false;
		}
	}
	
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
	
	private function deviceParser() {
		
		// build the obj that will be returned
		$deviceObj = new stdClass();
		
		// run the regexes to match things up
		$deviceRegexes = self::$regexes['device_parsers'];
		foreach ($deviceRegexes as $deviceRegex) {
			if (preg_match("/".str_replace("/","\/",$deviceRegex['regex'])."/",self::$ua,$matches)) {
				
				// basic properties
				$deviceObj->deviceMajor  = $deviceRegex['device_v1_replacement'] ? $deviceRegex['device_v1_replacement'] : $matches[2];
				$deviceObj->deviceMinor  = $deviceRegex['device_v2_replacement'] ? $deviceRegex['device_v2_replacement'] : $matches[3];
				$deviceObj->device       = $deviceRegex['device_replacement'] ? str_replace("$1",$deviceObj->deviceMajor,$deviceRegex['device_replacement']) : $matches[1];
				
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
}