<?php
//Anonsaba 2.0 configuration
	//Please don't change this...
	error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
	//error_reporting(E_ALL);
	ini_set('display_errors', 1);
	date_default_timezone_set('Etc/GMT+7');
	if (!headers_sent()) {
		header('Content-Type: text/html; charset=utf-8');
	}
	$config = array();
	//Installation password
	$config['installpass'] = '';
	//Database Configuration
	$config['type'] = 'mysql'; //MySQL currently only supported because fuck you
	$config['socket'] = false; //Is the DB host going to be a Unix Socket?
	$config['host'] = 'localhost';
	$config['database'] = 'anonsaba';//The database to use for Anonsaba
	$config['user'] = 'root';//Database username
	$config['pass'] = '';//Database password
	$config['prefix'] = '';//Prefix for the tables? IE-ANONSABA_
	$config['constant'] = false;//Want to have a constant connection to the MySQL server? I would just leave this set at false
	//Website paths
	$config['fullpath'] = realpath(dirname(__DIR__)).'/';//Dont even change this
	$config['url'] = 'http://localhost/';//Full URL including the trailing slash (Kusaba was stupid not to do this)
	$config['cookies'] = '.localhost';// http://www.somechan.org/ would be .somechan.org or http://prefix.somechan.org would be prefix.somechan.org
	//Twig paths
	$config['dir'] = $config['fullpath'].'pages';
	$config['cache'] = $config['fullpath'].'pages_cache';
	//Security measures
	$config['rootnum'] = 1;//How many root users do you want? I highly suggest only having one, but Anonsaba is all about you...
	$config['root'] = 'root';//The root username you want to use *IF USING MORE THAN ONE ROOT USER LEAVE THIS BLANK*
	//$config['mulroot'] = '';//If you intend on having more than one root put them all here seperate with commas
	$config['spam'] = true;//Do you want to use built in spam filter? (Idk why the hell i'm even adding this option)
	//Other configuration
	$config['salt'] = '';//Enter some random numbers here 15 or more will do (Dead serious it's gonna count for 15 so slam on your keyboard)
	$config['hash'] = '';//KU_RANDOMSEED but now called 'hash' because fuck you 15 or more
	//Debug mode (Yea I moved this back into the config file)
	$config['debug'] = false;

	//Just leave everything down here alone...
	if (!isset($db)) {
		require 'db.php';
		if ($config['socket']) {
			$dsn = $config['type'].':unix_socket='.$config['host'].';dbname='.$config['database'];
			$db = new Database($dsn, $config['user'], $config['pass']);
			if ($config['debug']) {
				require 'ddb.php';
				unset($db);
				$db = new DebugDatabase($dsn, $config['user'], $config['pass']);
				$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
			}
		} else {
			$dsn = $config['type'].':host='.$config['host'].';dbname='.$config['database'];
			$db = new Database($dsn, $config['user'], $config['pass']);
			if ($config['debug']) {
				require 'ddb.php';
				unset($db);
				$db = new DebugDatabase($dsn, $config['user'], $config['pass']);
				$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
			}
		}
	}
	if (!isset($twig)) {
		require $config['fullpath'].'modules/Twig/Autoloader.php';
		$loader = new Twig_Loader_Filesystem ($config['dir']);
		$twig = new Twig_Environment($loader, array('cache' => $config['cache'], 'auto_reload' => true));
	}
	//Now we define our $config values globally so other files can access them
	if ($config['rootnum'] > 1) {
		$config['root'] = serialize(array($config['mulroot']));
	}
	while (list($key, $value) = each($config)) {
		define($key, $value);
	}
	unset($config);
