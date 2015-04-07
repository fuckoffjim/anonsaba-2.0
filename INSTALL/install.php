<?php
	require realpath(dirname(__DIR__)).'/'.'config/config.php';
	require realpath(dirname(__DIR__)).'/'.'modules/anonsaba.php';
	$twig_data['url'] = url;
	$twig_data['success'] = 0;
	if (isset($_POST['submit1'])) {
		if($_POST['installpass'] == installpass) {
			$twig_data['success'] = 1;
			//Lets insert the SQL
			if (file_exists('anonsaba.sql') && (filesize('anonsaba.sql') > 0)) {
				$sqlfile = fopen('anonsaba.sql', 'r');
				$readdata = fread($sqlfile, filesize('anonsaba.sql'));
				$readdata = str_replace('PREFIX',prefix,$readdata);
				fclose($sqlfile);
			} else {
				AnonsabaCore::Error(':(', 'An error occured. anonsaba.sql does not exist in this directory or it is 0 bytes big :( Barring that, do you have read permissions for the directory?');
			}
			$db->Execute('ALTER DATABASE `'.database.'` CHARACTER SET utf8 COLLATE utf8_general_ci');
			$sqlarray = explode("\n", $readdata);
			foreach ($sqlarray as $key => $sqldata) {
				$sqldata = trim($sqldata);
				if (strstr($sqldata, '--') || strlen($sqldata) == 0) {
					unset($sqlarray[$key]);
				}
			}
			$readdata = implode('',$sqlarray);
			$sqlarray = explode(';',$readdata);
			foreach ($sqlarray as $sqldata) {
				$sqldata = trim($sqldata);
				if (strlen($sqldata) !== 0) { 
					$pos1 = strpos($sqldata, '`');
					$pos2 = strpos($sqldata, '`', $pos1 + 1);
					$tablename = substr($sqldata, $pos1+1, ($pos2-$pos1)-1);
					$db->Execute($sqldata);
				}
			}
			//End the SQL insert
		} else {
			AnonsabaCore::Error('Sorry!', 'Install password incorrect');
		}
	}
	if (isset($_POST['submit2'])) {
		$pass = AnonsabaCore::Encrypt($_POST['password']);
		$conf_names = array('sitename', 'slogan', 'irc', 'timgh', 'timgw', 'rimgh', 'rimgw', 'bm');
		$conf_values = array($_POST['sitename'], $_POST['slogan'], $_POST['irc'], $_POST['timgh'], $_POST['timgw'], $_POST['rimgh'], $_POST['rimgw'], $_POST['bm']);
		// Have to have two executes since the array starts at 0 and the for loop only counts down to 1
		$db->Execute('INSERT INTO `'.prefix.'siteconfig` (`config_value`, `config_name`) VALUES ('.$db->quote($conf_values[0]).', '.$db->quote($conf_names[0]).')');
		for ($i = 8; --$i;) {
			$db->GetAll('INSERT INTO `'.prefix.'siteconfig` (`config_value`, `config_name`) VALUES ('.$db->quote($conf_values[$i]).', '.$db->quote($conf_names[$i]).')');
		}
		$db->Execute('INSERT INTO `'.prefix.'staff` (`username`, `password`, `level`, `boards`) VALUES ('.$db->quote($_POST['username']).', '.$db->quote($pass).', "admin", "all")');
		$db->Execute('INSERT INTO `'.prefix.'siteconfig` (`config_value`, `config_name`) VALUES ('.$db->quote(time()).', "installtime")');
		$db->Execute('INSERT INTO `'.prefix.'siteconfig` (`config_value`, `config_name`) VALUES ("2.0", "version")');
		fopen(fullpath.'.installed', 'w');
		$twig_data['success'] = 2;
	}
	AnonsabaCore::Output('/install/install.tpl', $twig_data);
