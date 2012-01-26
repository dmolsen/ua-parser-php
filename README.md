# ua-parser-php #

ua-parser-php is a PHP-based pseudo-port of the [ua-parser](http://code.google.com/p/ua-parser/) project. ua-parser-php
utilizes the user agents regex YAML file from ua-parser but otherwise creates it's own properties for use in projects. ua-parser-php
was created as a new browser-detection library for the browser- and feature-detection library [Detector](https://github.com/dmolsen/Detector).

If you want, you can [test ua-parser-php](http://uaparser.dmolsen.com/) with your browser.

## Usage ##

Straightforward:

    <?php

       require("UAParser.php");
       $result = UA::parse();

       print $result->full;
       // -> Chrome 16.0.912/Mac OS X 10.6.8

       print $result->browserFull;
       // -> "Chrome 16.0.912"
		
       print $result->browser;
       // -> "Chrome"
		
       print $result->version;
       // -> "16.0.912"
		
       print $result->major;
       // -> 16 (minor, build, & revision also available)
		
       print $result->osFull;
       // -> "Mac OS X 10.6.8"
		
       print $result->os;
       // -> "Mac OS X"
		
       print $result->osVersion;
       // -> "10.6.8"
		
       print $result->osMajor;
       // -> 10 (osMinor, osBuild, & osRevision also available)

    ?>

## Credits ##

Thanks to the ua-parser team for making the YAML file available for others to build upon.