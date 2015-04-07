<?php
class Management {
//Anonsaba 2.0 Manage functions
	public static function LoginForm() {
		global $twig, $twig_data;
		$twig_data['sitename'] = AnonsabaCore::GetConfigOption('sitename');
		$twig_data['side'] = isset($_GET['side']) ? $_GET['side'] : 'main';
		$twig_data['action'] = isset($_GET['action']) ? $_GET['action'] : 'stats';
		AnonsabaCore::Output('/login.tpl', $twig_data);
	}
	public static function CheckLogin($side, $action) {
		global $db;
		$password = $db->GetOne('SELECT `password` FROM `'.prefix.'staff` WHERE `username` = '.$db->quote($_POST['username']));
		$passhash = AnonsabaCore::Encrypt($_POST['password']);
		$passuse = $db->GetOne('SELECT `active` FROM `'.prefix.'staff` WHERE `username` = '.$db->quote($_POST['username']));
		/*if (!$passuse || $passuse == 0) {
		display option to change password	
		}*/
		if ($db->GetOne('SELECT `suspended` FROM `'.prefix.'staff` WHERE `username` = '.$db->quote($_POST['username'])) == '1') {
			AnonsabaCore::Error('You are currently suspended');
		}
		if ($password == $passhash) {
			$_SESSION['manageusername'] = $_POST['username'];
			self::CreateSession($_POST['username']);
			AnonsabaCore::Log($_POST['username'], 'Logged in', time());
			header("Location: ".url.'/management/index.php?side='.$side.'&action='.$action.'');
		} else {
			AnonsabaCore::Log($_POST['username'], 'Failed login Attempt (IP: '.$_SERVER['REMOTE_ADDR'].')', time());
			AnonsabaCore::Error('Incorrect Username/Password', 'This has been logged');
		}
	}
	public static function Logout() {
		self::DestroySession($_SESSION['manageusername']);
		header("Location: ".url.'/management/');
	}
	public static function CreateSession($val) {
		global $db;
		$chars = hash;
		$sessionid = '';
		for ($i = 0; $i < strlen($chars); ++$i) {
			$sessionid .= $chars[mt_rand(0, strlen($chars) - 1)];
		}
		$_SESSION['sessionid'] = $sessionid;
		$_SESSION['manageusername'] = $val;
		setcookie("mod", "allboards", time() + 3600, '/', cookies);
		$db->Execute('UPDATE `'.prefix.'staff` SET `sessionid` = '.$db->quote($sessionid).' WHERE `username` = '.$db->quote($val));
		$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($val));
	}
	public static function DestroySession($val) {
		global $db;
		$db->Execute('UPDATE `'.prefix.'staff` SET `sessionid` = "" WHERE `username` = '.$db->quote($val));
		setcookie("mod", "allboards", time() - 3600, '/', cookies);
		unset($_SESSION['manageusername']);
		unset($_SESSION['sessionid']);
		session_destroy();
	}
	public static function ValidateSession($manage=false) {
		global $db;
		if (isset($_SESSION['sessionid']) && isset($_SESSION['manageusername'])) {
			$session = $db->GetOne('SELECT `sessionid` FROM `'.prefix.'staff` WHERE `username` = '.$db->quote($_SESSION['manageusername']));
			if ($_SESSION['sessionid'] != $session) {
				self::DestroySession($_SESSION['manageusername']);
				AnonsabaCore::Error('Invalid Session', '<a href="/index.php">Login again</a>');
			}
			return true;
		} else {
			if (!$manage) {
				die(self::LoginForm());
			} else {
				return false;
			}
		}
	}
	public static function GetStaffLevel($val) {
		global $db;
		return $db->GetOne('SELECT `level` FROM `'.prefix.'staff` WHERE `username` = '.$db->quote($val));
		
		
	}
	public static function stats() {
		global $db, $twig_data, $board_core;
		//begin memory test with loading boards
		$twig_data['memory'] = substr(memory_get_usage() / 1024 / 1024, 0, 4);
		$twig_data['peakmemory'] = self::memory();
		$twig_data['version'] = AnonsabaCore::GetConfigOption('version');
		$howlong = time() - AnonsabaCore::GetConfigOption('installtime');
		if ($howlong < 86400) {
			$twig_data['installdate'] = 'Today';
		} else {
			$twig_data['installdate'] = AnonsabaCore::GetConfigOption('installdate');
		}
		switch (type) {
			case 'mysql':
				$twig_data['databasetype'] = 'MySQL';
			break;
		}
		$twig_data['boardnum'] = $db->GetOne('SELECT COUNT(*) FROM `'.prefix.'boards`');
		$twig_data['numpost'] = $db->GetOne('SELECT COUNT(*) FROM `'.prefix.'posts`');
		AnonsabaCore::Output('/manage/main/welcome.tpl', $twig_data);
	}
	public static function graph() {
		global $twig_data;
		$twig_data['page'] = '';
	}
	public static function changepass() {
		global $db, $twig_data;
		$twig_data['message'] = '';
		if(isset($_POST['submit'])) {
			self::ValidateSession();
			if (AnonsabaCore::Encrypt($_POST['oldpass']) != $db->GetOne('SELECT `password` FROM `'.prefix.'staff` WHERE `username` = '.$db->quote($_SESSION['manageusername']).'')) {
				$twig_data['message'] = '<font color="red">The supplied password didn\'t match the one stored in the Database</font>';
			} elseif($_POST['newpass'] != $_POST['newpass2'])  {
				$twig_data['message'] = '<font color="red">The supplied new passwords do not match</font>';
			} elseif ($_POST['newpass'] == $_POST['newpass2'] && AnonsabaCore::Encrypt($_POST['oldpass']) == $db->GetOne('SELECT `password` FROM `'.prefix.'staff` WHERE `username` = '.$db->quote($_SESSION['manageusername']).'')) {
				$twig_data['message'] = '<font color="green">Password Successfully changed</font>';
				$db->Execute('UPDATE `'.prefix.'staff` SET `password` = '.$db->quote(AnonsabaCore::Encrypt($_POST['newpass'])).' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
			}
		}
		AnonsabaCore::OutPut('/manage/main/changepass.tpl', $twig_data);
	}
	public static function pp() {
		global $db, $twig_data;
		$twig_data['postingpass'] = $_SESSION['sessionid'];
		AnonsabaCore::OutPut('/manage/main/pp.tpl', $twig_data);
	}
	public static function pms() {
		global $db, $twig_data;
		$twig_data['users'] = $db->GetAll('SELECT `username` FROM `'.prefix.'staff`');
		$twig_data['do'] = $_GET['do'];
		if (isset($_POST['submit'])) {
			if ($_POST['message'] != '' && $_POST['subject']) {
				$db->Execute('INSERT INTO `'.prefix.'pms` (`to`, `from`, `message`, `subject`, `time`) VALUES ('.$db->quote($_POST['to']).', '.$db->quote($_SESSION['manageusername']).', '.$db->quote($_POST['message']).', '.$db->quote($_POST['subject']).', '.time().')');
				$twig_data['msg'] = '<font color="green">PM successfully sent to '.$_POST['to'].'.</font>';
			} else {
				$twig_data['msg'] = '<font color="red">Please enter a Subject and Message to send a PM.</font>';
			}
			$twig_data['messages'] = $db->GetAll('SELECT * FROM `'.prefix.'pms` WHERE `to` = '.$db->quote($_SESSION['manageusername']).' ORDER BY `read` ASC, `time` DESC');
			AnonsabaCore::OutPut('/manage/main/pms.tpl', $twig_data);
		} elseif ($_GET['do'] == 'del') {
			$db->Execute('DELETE FROM `'.prefix.'pms` WHERE `id` = '.$_GET['id']);
			$twig_data['msg'] = '<font color="green">Successfully delete message!</font>';
			$twig_data['messages'] = $db->GetAll('SELECT * FROM `'.prefix.'pms` WHERE `to` = '.$db->quote($_SESSION['manageusername']).' ORDER BY `read` ASC, `time` DESC');
		} elseif ($_GET['do'] == 'read') {
			$db->Execute('UPDATE `'.prefix.'pms` SET `read` = 1 WHERE `id` = '.$_GET['id']);
			$twig_data['msg'] = '<font color="green">Successfully marked message as read!</font>';
			$twig_data['messages'] = $db->GetAll('SELECT * FROM `'.prefix.'pms` WHERE `to` = '.$db->quote($_SESSION['manageusername']).' ORDER BY `read` ASC, `time` DESC');
		} elseif ($_GET['do'] == 'reply') {
			$db->Execute('UPDATE `'.prefix.'pms` SET `read` = 1 WHERE `id` = '.$_GET['id']);
			$twig_data['messages'] = $db->GetAll('SELECT * FROM `'.prefix.'pms` WHERE `id` = '.$_GET['id']);
		} else {
			$twig_data['messages'] = $db->GetAll('SELECT * FROM `'.prefix.'pms` WHERE `to` = '.$db->quote($_SESSION['manageusername']).' ORDER BY `read` ASC, `time` DESC');
		}
		AnonsabaCore::OutPut('/manage/main/pms.tpl', $twig_data);	
	}
	public static function news() {
		global $db, $twig_data;
		$twig_data['message'] = '';
		$twig_data['do'] = isset($_GET['do']) ? $_GET['do'] : '';
		if (isset($_POST['submit']) && $_POST['edit'] == '') {
			if ($_POST['subject'] != '') {
				$db->Execute('INSERT INTO `'.prefix.'front` (`by`, `message`, `date`, `type`, `subject`, `email`) VALUES ('.$db->quote($_SESSION['manageusername']).', '.$db->quote($_POST['message']).', '.$db->quote(time()).', '.$db->quote('news').', '.$db->quote($_POST['subject']).', '.$db->quote($_POST['email']).')');
				AnonsabaCore::Log($_SESSION['manageusername'], 'Added a News item', time());
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				$twig_data['message'] = '<font color="green">News item successfully created</font>';
			} else {
				$twig_data['message'] = '<font color="red">Please enter a Subject</font>';
			}
		} elseif (isset($_POST['submit']) && $_POST['edit'] != '') {
			if ($_POST['subject'] != '') {
				$db->Execute('UPDATE `'.prefix.'front` SET `message` = '.$db->quote($_POST['message']).', `subject` = '.$db->quote($_POST['subject']).', `email` = '.$db->quote($_POST['email']).' WHERE `id` = '.$_GET['id'].' AND `type` = '.$db->quote('news'));
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				$twig_data['message'] = '<font color="green">News item successfully edited</font>';
			} else {
				$twig_data['message'] = '<font color="red">Please enter a Subject</font>';
			}
		}
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'edit') {
				$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'front` WHERE `id` = '.$_GET['id'].' AND `type` = '.$db->quote('news'));
			} elseif ($_GET['do'] == 'del') {
				$db->Execute('DELETE FROM `'.prefix.'front` WHERE `id` = '.$_GET['id'].' AND `type` = '.$db->quote('news'));
				AnonsabaCore::Log($_SESSION['manageusername'], 'Deleted a News item', time());
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				$twig_data['message'] = '<font color="green">News item successfully deleted</font>';
			} 
		}
		$twig_data['entries'] = $db->GetAll('SELECT * FROM `'.prefix.'front` WHERE `type` = '.$db->quote('news'));
		AnonsabaCore::Output('/manage/site/news.tpl', $twig_data);
	}
	public static function rules() {
		global $db, $twig_data;
		$twig_data['message'] = '';
		$twig_data['do'] = isset($_GET['do']) ? $_GET['do'] : '';
		if (isset($_POST['submit']) && $_POST['edit'] == '') {
if ($_POST['subject'] != '') {
				$db->Execute('INSERT INTO `'.prefix.'front` (`by`, `message`, `type`, `subject`) VALUES ('.$db->quote($_SESSION['manageusername']).', '.$db->quote($_POST['message']).', '.$db->quote('rules').', '.$db->quote($_POST['subject']).')');
				AnonsabaCore::Log($_SESSION['manageusername'], 'Added a Rules item', time());
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				$twig_data['message'] = '<font color="green">Rule item successfully created</font>';
			} else {
				$twig_data['message'] = '<font color="red">Please enter a Rule subject</font>';
			}
		} elseif (isset($_POST['submit']) && $_POST['edit'] != '') {
			if ($_POST['subject'] != '') {
				$db->Execute('UPDATE `'.prefix.'front` SET `message` = '.$db->quote($_POST['message']).', `subject` = '.$db->quote($_POST['subject']).' WHERE `id` = '.$_GET['id'].' AND `type` = '.$db->quote('rules'));
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				$twig_data['message'] = '<font color="green">Rule item successfully edited</font>';
			} else {
				$twig_data['message'] = '<font color="red">Please enter a Rule subject</font>';
			}
		}
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'edit') {
				$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'front` WHERE `id` = '.$_GET['id'].' AND `type` = '.$db->quote('rules'));
			} elseif ($_GET['do'] == 'del') {
				$db->Execute('DELETE FROM `'.prefix.'front` WHERE `id` = '.$_GET['id'].' AND `type` = '.$db->quote('rules'));
				AnonsabaCore::Log($_SESSION['manageusername'], 'Deleted a Rules item', time());
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				$twig_data['message'] = '<font color="green">Rule item successfully deleted</font>';
			} 
		}
		$twig_data['entries'] = $db->GetAll('SELECT * FROM `'.prefix.'front` WHERE `type` = '.$db->quote('rules'));
		AnonsabaCore::Output('/manage/site/rules.tpl', $twig_data);
	}
	public static function faq() {
		global $db, $twig_data;
		$twig_data['message'] = '';
		$twig_data['do'] = isset($_GET['do']) ? $_GET['do'] : '';
		if (isset($_POST['submit']) && $_POST['edit'] == '') {
if ($_POST['subject'] != '') {
				$db->Execute('INSERT INTO `'.prefix.'front` (`by`, `message`, `type`, `subject`) VALUES ('.$db->quote($_SESSION['manageusername']).', '.$db->quote($_POST['message']).', '.$db->quote('faq').', '.$db->quote($_POST['subject']).')');
				AnonsabaCore::Log($_SESSION['manageusername'], 'Added a FAQ item', time());
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				$twig_data['message'] = '<font color="green">FAQ item successfully created</font>';
			} else {
				$twig_data['message'] = '<font color="red">Please enter a FAQ subject</font>';
			}
		} elseif (isset($_POST['submit']) && $_POST['edit'] != '') {
			if ($_POST['subject'] != '') {
				$db->Execute('UPDATE `'.prefix.'front` SET `message` = '.$db->quote($_POST['message']).', `subject` = '.$db->quote($_POST['subject']).' WHERE `id` = '.$_GET['id'].' AND `type` = '.$db->quote('faq'));
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				$twig_data['message'] = '<font color="green">FAQ item successfully edited</font>';
			} else {
				$twig_data['message'] = '<font color="red">Please enter a FAQ subject</font>';
			}
		}
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'edit') {
				$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'front` WHERE `id` = '.$_GET['id'].' AND `type` = '.$db->quote('faq'));
			} elseif ($_GET['do'] == 'del') {
				$db->Execute('DELETE FROM `'.prefix.'front` WHERE `id` = '.$_GET['id'].' AND `type` = '.$db->quote('faq'));
				AnonsabaCore::Log($_SESSION['manageusername'], 'Deleted a FAQ item', time());
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				$twig_data['message'] = '<font color="green">FAQ item successfully deleted</font>';
			} 
		}
		$twig_data['entries'] = $db->GetAll('SELECT * FROM `'.prefix.'front` WHERE `type` = '.$db->quote('faq'));
		AnonsabaCore::Output('/manage/site/faq.tpl', $twig_data);
	}
	public static function logs() {
		global $db, $twig_data;
		$pages = $db->GetOne('SELECT COUNT(*) FROM `'.prefix.'logs`');
		$twig_data['page'] = $_GET['page'];
		$twig_data['pages'] = ($pages/25);
		if (rootnum < 2) {
			if ($_SESSION['manageusername'] == root) {
				$twig_data['root'] = '1';
			}
		} else {
			if (in_array($_SESSION['manageusername'], unserialize($root))) {
				$twig_data['root'] = '1';
			}
		}
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'del') {
				if ($_GET['id'] == 'all') {
					$db->Execute('DELETE FROM `'.prefix.'logs`');
					AnonsabaCore::Log($_SESSION['manageusername'], 'Deleted all Log items', time());
				} else {
					$db->Execute('DELETE FROM `'.prefix.'logs` WHERE `id` = '.$db->quote($_GET['id']));
					AnonsabaCore::Log($_SESSION['manageusername'], 'Deleted a Log item', time());
				}
				$twig_data['message'] = '<font color="green">Log item successfully deleted</font>';
			}
			$twig_data['entries'] = $db->GetAll('SELECT * FROM `'.prefix.'logs` ORDER BY `time` DESC LIMIT 25 OFFSET '.($_GET['page'] * 25));
		} else {
			$twig_data['entries'] = $db->GetAll('SELECT * FROM `'.prefix.'logs` ORDER BY `time` DESC LIMIT 25 OFFSET '.($_GET['page'] * 25));
		}
		AnonsabaCore::Output('/manage/site/logs.tpl', $twig_data);
	}
	public static function staff() {
		global $db, $twig_data;
		$twig_data['do'] = isset($_GET['do']) ? $_GET['do'] : '';
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'del') {
				$twig_data['entries'] = $db->GetAll('SELECT * FROM `'.prefix.'staff`');
				$boards = $db->GetAll('SELECT `boards` FROM `'.prefix.'staff`');
				$twig_data['staffboard'] = '<strong>/'. implode('/</strong>, <strong>/', explode('|', $boards)) . '/</strong>';
				AnonsabaCore::Log($_SESSION['manageusername'], 'Deleted staff member ('.$db->GetOne('SELECT `username` FROM `'.prefix.'staff` WHERE `id` = '.$_GET['id']).')', time());
				$db->Execute('DELETE FROM `'.prefix.'staff` WHERE `id` = '.$_GET['id']);
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				$twig_data['message'] = '<font color="green">Staff member successfully deleted</font>';
				AnonsabaCore::Output('/manage/site/staff.tpl', $twig_data);
			} elseif ($_GET['do'] == 'add') {
				$twig_data['entries'] = $db->GetAll('SELECT * FROM `'.prefix.'staff`');
				$boards = $db->GetAll('SELECT `boards` FROM `'.prefix.'staff`');
				$twig_data['staffboard'] = '<strong>/'. implode('/</strong>, <strong>/', explode('|', $boards)) . '/</strong>';
				$db->Execute('INSERT INTO `'.prefix.'staff` (`username`, `password`, `level`, `suspended`) VALUES ('.$db->quote($_POST['username']).', '.$db->quote(AnonsabaCore::Encrypt($_POST['password'])).', '.$db->quote($_POST['level']).', 0)');
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				AnonsabaCore::Log($_SESSION['manageusername'], 'Added staff member ('.$_POST['username']. ')', time());
				$twig_data['message'] = '<font color="green">Staff member created successfully</font>';
				AnonsabaCore::Output('/manage/site/staff.tpl', $twig_data);
			} elseif ($_GET['do'] == 'suspend') {
				$twig_data['entries'] = $db->GetAll('SELECT * FROM `'.prefix.'staff`');
				$boards = $db->GetAll('SELECT `boards` FROM `'.prefix.'staff`');
				$twig_data['staffboard'] = '<strong>/'. implode('/</strong>, <strong>/', explode('|', $boards)) . '/</strong>';
				$db->Execute('UPDATE `'.prefix.'staff` SET `suspended` = 1 WHERE `id` = '.$_GET['id']);
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				AnonsabaCore::Log($_SESSION['manageusername'], 'Suspended staff member ('.$db->GetOne('SELECT `username` FROM `'.prefix.'staff` WHERE `id` = '.$_GET['id']). ')', time());
				$twig_data['message'] = '<font color="green">Staff member suspended successfully</font>';
				AnonsabaCore::Output('/manage/site/staff.tpl', $twig_data);
			} elseif ($_GET['do'] == 'unsuspend') {
				$twig_data['entries'] = $db->GetAll('SELECT * FROM `'.prefix.'staff`');
				$boards = $db->GetAll('SELECT `boards` FROM `'.prefix.'staff`');
				$twig_data['staffboard'] = '<strong>/'. implode('/</strong>, <strong>/', explode('|', $boards)) . '/</strong>';
				$db->Execute('UPDATE `'.prefix.'staff` SET `suspended` = 0 WHERE `id` = '.$_GET['id']);
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				AnonsabaCore::Log($_SESSION['manageusername'], 'Unsuspended staff member ('.$db->GetOne('SELECT `username` FROM `'.prefix.'staff` WHERE `id` = '.$_GET['id']). ')', time());
				$twig_data['message'] = '<font color="green">Staff member unsuspended successfully</font>';
				AnonsabaCore::Output('/manage/site/staff.tpl', $twig_data);	
			} elseif ($_GET['do'] == 'edit') {
				$twig_data['boards'] = $db->Getall('SELECT * FROM `'.prefix.'boards` ORDER BY `name`');
				$twig_data['staff'] = $db->GetAll('SELECT * FROM `'.prefix.'staff` WHERE `id` = '.$db->quote($_GET['id']));
				if (isset($_POST['submit'])) {
					if (isset($_POST['all'])) {
						$changed_boards = array('allboards');
					} else {
						$changed_boards = array();
						while (list($postkey, $postvalue) = each($_POST)) {
							if (substr($postkey, 0, 4) == "mods") {
								$changed_boards = array_merge($changed_boards, array(substr($postkey, 4)));
							}
						}
					}
					$db->Execute('UPDATE `'.prefix.'staff` SET `boards` = '.$db->quote(implode('|', $changed_boards)).', `level` = '.$db->quote($_POST['level']).' WHERE `id` = '.$_GET['id']);
					$twig_data['message'] = '<font color="green">Staff member successfully edited!</font>';
				}
			}
			$twig_data['entries'] = $db->GetAll('SELECT * FROM `'.prefix.'staff`');
			$boards = $db->GetAll('SELECT `boards` FROM `'.prefix.'staff`');
			$twig_data['staffboard'] = '<strong>/'. implode('/</strong>, <strong>/', explode('|', $boards)) . '/</strong>';
		} else {
			$twig_data['entries'] = $db->GetAll('SELECT * FROM `'.prefix.'staff`');
			$boards = $db->GetAll('SELECT `boards` FROM `'.prefix.'staff`');
			$twig_data['staffboard'] = '<strong>/'. implode('/</strong>, <strong>/', explode('|', $boards)) . '/</strong>';
			AnonsabaCore::Output('/manage/site/staff.tpl', $twig_data);
		}
	}
	public static function clean() {
		global $db, $twig_data;
		if (isset($_POST['cleanup'])) {
			$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
			AnonsabaCore::Log($_SESSION['manageusername'], 'Ran cleanup', time());
			$board_core = new BoardCore();
			$timestart = $board_core->microtime_float();
			$twig_data['twigcache'] = '1';
			$boards = $db->GetAll("SELECT * FROM `boards`");
			foreach ($boards as $data) {
				$board_core->Board($data['name']);
				$board_core->RefreshAll();
			}
			$twig_data['twigdone'] = '1';
			$twig_data['cleansql'] = '1';
			$tables = $db->GetAll('SHOW TABLES');
			foreach ($tables as $line) {
				//Changed to GetAll because PDO hates unbuffered queries
				$db->GetAll('OPTIMIZE TABLE `'.$line['Tables_in_'.database].'`');
			}
			$twig_data['sqldone'] = '1';
			$twig_data['howlong'] = round($board_core->microtime_float() - $timestart, 4);
		}
		AnonsabaCore::Output('/manage/site/cleanup.tpl', $twig_data);
	}
	public static function adddelboard()  {
		global $db, $twig_data;
		$twig_data['message'] = '';
		if (isset($_POST['submit'])) {
			if (isset($_POST['desc']) || isset($_POST['dir'])) {
				$twig_data['message'] = self::addboard($_POST['dir'], $_POST['desc']);
			} else {
				$twig_data['message'] = '<font color="red">Please enter the directory and board name</font>';
			}
			$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'boards` ORDER BY `name`');
			AnonsabaCore::Output('/manage/board/adddelboard.tpl', $twig_data);
		} elseif (isset($_POST['delete'])) {
			$twig_data['message'] = self::delboard($_POST['delboard']);
			$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'boards` ORDER BY `name`');
			AnonsabaCore::Output('/manage/board/adddelboard.tpl', $twig_data);
		} else {
			$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'boards` ORDER BY `name`');
			AnonsabaCore::Output('/manage/board/adddelboard.tpl', $twig_data);
		}
	}
	public static function addboard($val, $val2) {
		global $db, $twig_data;
		$boardexist = $db->GetOne('SELECT COUNT(*) FROM `'.prefix.'boards` WHERE `name` = '.$db->quote($val));
		if ($boardexist == 0) {
			if (mkdir(fullpath.$val, $mode = 0755) && mkdir(fullpath.$val.'/src', $mode = 0755) && mkdir(fullpath.$val.'/res', $mode = 0755)) {
				$db->Execute('INSERT INTO `'.prefix.'boards` (`name`, `desc`) VALUES ('.$db->quote($val).', '.$db->quote($val2).')');
				$boardid = $db->lastInsertId();
				file_put_contents(fullpath. $val .'/.htaccess' , 'DirectoryIndex board.html');
				$filetypes = $db->GetAll('SELECT '.prefix.'filetypes.id FROM '.prefix.'filetypes WHERE '.prefix.'filetypes.name = "JPG" OR '.prefix.'filetypes.name = "GIF" OR '.prefix.'filetypes.name = "PNG"');
				unset($board_core);
				foreach ($filetypes as $filetype) {
					$db->Execute('INSERT INTO `'.prefix.'board_filetypes` (`boardid`, `fileid`) VALUES ('.$boardid.', '.$filetype['id'].')');
				}
				$board_core = new BoardCore();
				$board_core->Board($val);
				$board_core->RefreshAll();
				file_put_contents(fullpath . $val . '/src/.htaccess', 'AddType text/plain .ASM .C .CPP .CSS .JAVA .JS .LSP .PHP .PL .PY .RAR .SCM .TXT'. "\n" . 'SetHandler default-handler');
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				AnonsabaCore::Log($_SESSION['manageusername'], 'Created board /'.$val.'/', time());
				return '<font color="green">Board successfully created</font>';
			} else {
				return '<font color="red">CHANGE YOUR PERMISSIONS FAGGOT</font>';	
			}
		} else {
			return '<font color="red">That board already exists.</font>';
		}
	}
	public static function delboard($val) {
		global $db, $twig_data;
		AnonsabaCore::rrmdir(fullpath.$val);
		$id = $db->GetOne('SELECT `id` FROM `'.prefix.'boards` WHERE `name` = '.$db->quote($val));
		$db->Execute('DELETE FROM `'.prefix.'board_filetypes` WHERE `boardid` = '.$id);
		$db->Execute('DELETE FROM `'.prefix.'boards` WHERE `name` = '.$db->quote($val));
		$db->Execute('DELETE FROM `'.prefix.'posts` WHERE `boardname` = '.$db->quote($val));
		$db->Execute('DELETE FROM `'.prefix.'files` WHERE `board` = '.$db->quote($val));
		$db->Execute('DELETE FROM `'.prefix.'wordfilters` WHERE `boards` = '.$db->quote($val));
		$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
		AnonsabaCore::Log($_SESSION['manageusername'], 'Deleted board /'.$val.'/', time());
		return '<font color="green">Board successfully deleted</font>';
	}
	public static function boardopt() {
		global $db, $twig_data;
		$twig_data['list'] = $db->GetAll('SELECT * FROM `'.prefix.'boards` ORDER BY `name`');
		$twig_data['do'] = isset($_GET['do']) ? $_GET['do'] : '';
		if (isset($_GET['do'])) {
			$twig_data['boardopts'] = $db->GetAll('SELECT * FROM `'.prefix.'boards` WHERE `name` = '.$db->quote($_POST['boards']));
			$filetypes = $db->GetAll('SELECT * FROM `'.prefix.'filetypes`');
			$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'sections`');
			$tpl_page = '';
			foreach ($twig_data['boardopts'] as $lineboard) {
				foreach ($filetypes as $types) {
					$tpl_page .= '<label for="filetype_'.$types['id'] . '">'. strtoupper($types['name']) . '</label><input type="checkbox" name="filetype_'. $types['id'] . '"';
					$filetype_isenabled = $db->GetOne('SELECT COUNT(*) FROM `'.prefix.'board_filetypes` WHERE `boardid` = '.$lineboard['id'].' AND `fileid` = '.$types['id']);
					if ($filetype_isenabled == 1) {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' /><br />';
				}
			}
			$twig_data['thingy'] = $tpl_page;
			if (isset($_POST['submit'])) {
				$type = array();
				while (list($postkey, $postvalue) = each($_POST)) {
					if (substr($postkey, 0, 9) == 'filetype_') {
						$type[] = substr($postkey, 9);
					}
				}
				$db->Execute('DELETE FROM `'.prefix.'board_filetypes` WHERE `boardid` = '.$_POST['edit']);
				foreach ($type as $line) {
					$db->Execute('INSERT INTO `'.prefix.'board_filetypes` (`boardid`, `fileid`) VALUES ('.$_POST['edit'].', '.$line.')');
				}
				//OH GOD THIS QUERY IS HUGE
				$fileurl = isset($_POST['fileurl']) ? '1' : '0';
				$locked = isset($_POST['locked']) ? '1' : '0';
				$email = isset($_POST['email']) ? '1' : '0';
				$ads = isset($_POST['ads']) ? '1' : '0';
				$showid = isset($_POST['showid']) ? '1' : '0';
				$report = isset($_POST['report']) ? '1' : '0';
				$captcha = isset($_POST['captcha']) ? '1' : '0';
				$nofile = isset($_POST['nofile']) ? '1' : '0';
				$forcedanon = isset($_POST['forcedanon']) ? '1' : '0';
				$trail = isset($_POST['trail']) ? '1' : '0';
				$popular = isset($_POST['popular']) ? '1' : '0';
				$recentpost = isset($_POST['recentpost']) ? '1' : '0';
				$db->Execute('UPDATE `'.prefix.'boards` SET `desc` = '.$db->quote($_POST['desc']).', `class` = '.$_POST['class'].', `section` = '.$db->quote($_POST['section']).', `header` = '.$db->quote($_POST['header']).', `fileurl` = '.$fileurl.', `fileperpost` = '.$_POST['fileperpost'].', `imagesize` = '.$_POST['imagesize'].', `postperpage` = '.$_POST['postperpage'].', `boardpages` = '.$_POST['maxboardpage'].', `threadhours` = '.$_POST['threadhours'].', `markpage` = '.$_POST['markpage'].', `threadreply` = '.$_POST['threadreply'].', `postername` = '.$db->quote($_POST['postername']).', `locked` = '.$locked.', `email` = '.$email.', `ads` = '.$ads.', `showid` = '.$showid.', `report` = '.$report.', `captcha` = '.$captcha.', `nofile` = '.$nofile.', `forcedanon` = '.$forcedanon.', `trail` = '.$trail.', `popular` = '.$popular.', `recentpost` = '.$recentpost.' WHERE `id` = '.$_POST['edit']);
				$twig_data['message'] = '<font color="green">Successfully edited board!</font>';
			}
		}
		AnonsabaCore::Output('/manage/board/boardopt.tpl', $twig_data);		
	}
	public static function sections() {
		global $db, $twig_data;
		$twig_data['message'] = '';
		$twig_data['do'] = isset($_GET['do']) ? $_GET['do'] : '';
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'add' && $_POST['name'] != '' && $_POST['abbr'] != '' && is_numeric($_POST['order'])) {
				$hidden = isset($_POST['hidden']) ? '1' : '0';
				$nameexist = $db->GetOne('SELECT COUNT(*) FROM `'.prefix.'sections` WHERE `name` = '.$db->quote($_POST['name']));
				$abbrexist = $db->GetOne('SELECT COUNT(*) FROM `'.prefix.'sections` WHERE `abbr` = '.$db->quote($_POST['abbr']));
				if ($nameexist >= 1 || $abbrexist >= 1) {
					$twig_data['message'] = '<font color="red">That section already exists.</font>';
				} else {
					$db->Execute('INSERT INTO `'.prefix.'sections` (`order`, `abbr`, `name`, `hidden`) VALUES ('.$_POST['order'].', '.$db->quote($_POST['abbr']).', '.$db->quote($_POST['name']).', '.$hidden.')');
					$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
					AnonsabaCore::Log($_SESSION['manageusername'], 'Created section ('.$_POST['name'].')', time());
					$twig_data['message'] = '<font color="green">Section successfully created!</font>';
				}
			} elseif ($_GET['do'] == 'del') {
				AnonsabaCore::Log($_SESSION['manageusername'], 'Deleted section ('.$db->GetOne('SELECT `name` FROM `'.prefix.'sections` WHERE `id` = '.$_GET['id']).')', time());
				$db->Execute('DELETE FROM `'.prefix.'sections` WHERE `id` = '.$_GET['id']);
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				$twig_data['message'] = '<font color="green">Section successfully deleted.</font>';
			} elseif ($_GET['do'] == 'edit') {
				$twig_data['entrys'] = $db->GetAll('SELECT * FROM `'.prefix.'sections` WHERE `id` = '.$_GET['id']);
				if (isset($_POST['submit']) && $_POST['name'] != '' && $_POST['abbr'] != '' && is_numeric($_POST['order'])) {
					$hidden = isset($_POST['hidden']) ? $_POST['hidden'] : '0';
					$db->Execute('UPDATE `'.prefix.'sections` SET `order` = '.$_POST['order'].', `name` = '.$db->quote($_POST['name']).', `abbr` = '.$db->quote($_POST['abbr']).', `hidden` = '.$hidden.' WHERE `id` = '.$_GET['id']);
					$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
					$twig_data['message'] = '<font color="green">Section successfully edited.</font>';
				}
			} elseif ($_POST['name'] == '' || $_POST['abbr'] == '') {
				$twig_data['message'] = '<font color="red">Please enter a Section name and Abbreviation</font>';
			} elseif (!is_numeric($_POST['order'])) {
				$twig_data['message'] = '<font color="red">Please enter a number for order</font>';
			}
			$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'sections` ORDER BY `order` ASC');
			AnonsabaCore::Output('/manage/board/sections.tpl', $twig_data);
		} else {
			$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'sections` ORDER BY `order` ASC');
			AnonsabaCore::Output('/manage/board/sections.tpl', $twig_data);
		}
	}
	public static function filetypes() {
		global $db, $twig_data;
		$twig_data['do'] = isset($_GET['do']) ? $_GET['do'] : '';
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'add') {
				if ($_POST['name'] != '') {
					$db->Execute('INSERT INTO `'.prefix.'filetypes` (`name`, `image`) VALUES ('.$db->quote($_POST['name']).', '.$db->quote($_POST['image']).')');
					$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
					$twig_data['message'] = '<font color="green">Filetype successfully created!</font>';
				} else {
					$twig_dat['message'] = '<font color="red">Please enter a filetype</font>';
				}
			} elseif ($_GET['do'] == 'edit') {
				$twig_data['entrys'] = $db->GetAll('SELECT * FROM `'.prefix.'filetypes` WHERE `id` = '.$_GET['id']);
				if (isset($_POST['submit'])) {
					$db->Execute('UPDATE `'.prefix.'filetypes` SET `name` = '.$db->quote($_POST['name']).', `image` = '.$db->quote($_POST['image']).' WHERE `id` = '.$_GET['id']);
					$twig_data['message'] = '<font color="green">Filetype successfully edited</font>';
				}
			} elseif ($_GET['do'] == 'del') {
				$db->Execute('DELETE FROM `'.prefix.'filetypes` WHERE `id` = '.$_GET['id']);
				$twig_data['message'] = '<font color="green">Filetype successfully deleted</font>';
			}
			$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'filetypes`');
			AnonsabaCore::Output('/manage/board/filetypes.tpl', $twig_data);
		} else {
			$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'filetypes`');
			AnonsabaCore::Output('/manage/board/filetypes.tpl', $twig_data);
		}
	}
	public static function wf() {
		global $db, $twig_data;
		$twig_data['boards'] = $db->GetAll('SELECT * FROM `'.prefix.'boards`');
		$twig_data['do'] = isset($_GET['do']) ? $_GET['do'] : '';
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'add') {
				if (isset($_POST['all'])) {
					$changed_boards = array('all');
				} else {
					$changed_boards = array();
					while (list($postkey, $postvalue) = each($_POST)) {
						if (substr($postkey, 0, 5) == "words") {
							$changed_boards = array_merge($changed_boards, array(substr($postkey, 5)));
						}
					}
				}
				$db->Execute('INSERT INTO `'.prefix.'wordfilters` (`word`, `replace`, `boards`) VALUES ('.$db->quote($_POST['word']).', '.$db->quote($_POST['replace']).', '.$db->quote(implode('|', $changed_boards)).')');
				$db->Execute('UPDATE `'.prefix.'staff` SET `active` = '.time().' WHERE `username` = '.$db->quote($_SESSION['manageusername']));
				AnonsabaCore::Log($_SESSION['manageusername'], 'Added wordfilter', time());
				$twig_data['message'] = '<font color="green">Wordfilter successfully added!</font>';
			} elseif ($_GET['do'] == 'del') {
				$db->Execute('DELETE FROM `'.prefix.'wordfilters` WHERE `id` = '.$_GET['id']);
				$twig_data['message'] = '<font color="green">Wordfilter successfully deleted!</font>';
			} elseif ($_GET['do'] == 'edit') {
				$twig_data['entrys'] = $db->GetAll('SELECT * FROM `'.prefix.'wordfilters` WHERE `id` = '.$_GET['id']);
				if (isset($_POST['all'])) {
					$changed_boards = array('all');
				} else {
					$changed_boards = array();
					while (list($postkey, $postvalue) = each($_POST)) {
						if (substr($postkey, 0, 5) == "words") {
							$changed_boards = array_merge($changed_boards, array(substr($postkey, 5)));
						}
					}
				}
			}
			$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'wordfilters`');
			AnonsabaCore::Output('/manage/board/wf.tpl', $twig_data);
		} else {
			$twig_data['entry'] = $db->GetAll('SELECT * FROM `'.prefix.'wordfilters`');
			AnonsabaCore::Output('/manage/board/wf.tpl', $twig_data);
		}
	}
	public static function recentpost() {
		global $db, $twig_data;
		$twig_data['url'] = url;
		$pages = $db->GetOne('SELECT COUNT(*) FROM `'.prefix.'posts` WHERE `deleted` = 0 AND `cleared` = 0');
		$twig_data['page'] = $_GET['page'];
		$twig_data['pages'] = ($pages/10);
		if (isset($_POST['clear'])) {
			$db->Execute('UPDATE `'.prefix.'posts` SET `cleared` = 1 WHERE `cleared` = 0');
			$twig_data['message'] = '<font color="green">All post reviewed!</font>';
			$twig_data['posts'] = $db->GetAll('SELECT * FROM `'.prefix.'posts` WHERE `deleted` = 0 AND `cleared` = 0 ORDER BY `time` DESC LIMIT 10 OFFSET '.($_GET['page'] * 10));
			$twig_data['files'] = $db->GetAll('SELECT * FROM `'.prefix.'files`');
		} else {
			$twig_data['posts'] = $db->GetAll('SELECT * FROM `'.prefix.'posts` WHERE `deleted` = 0 AND `cleared` = 0 ORDER BY `time` DESC LIMIT 10 OFFSET '.($_GET['page'] * 10));
			$twig_data['files'] = $db->GetAll('SELECT * FROM `'.prefix.'files`');
		}
		AnonsabaCore::Output('/manage/moderation/rp.tpl', $twig_data);
	}
	public static function del() {
		global $db;
		$db->Execute('DELETE FROM `'.prefix.'posts` WHERE `id` = '.$_GET['id'].' AND `boardname` = '.$db->quote($_GET['boardname']));
		$db->Execute('DELETE FROM `'.prefix.'posts` WHERE `parent` = '.$_GET['id'].' AND `boardname` = '.$db->quote($_GET['boardname']));
		$board_core = new BoardCore();
		$board_core->Board($_GET['boardname']);
		$board_core->RefreshAll();
		header("Location: ".url.$_GET['boardname']); 
	}
	public static function delip() {
		global $db;
		$db->Execute('DELETE FROM `'.prefix.'posts` WHERE `ip` = '.$_GET['ip']);
		$board_core = new BoardCore();
		$board_core->Board($_GET['boardname']);
		$board_core->RefreshAll();
		header("Location: ".url.$_GET['boardname']);
	}
	public static function getip() {
		global $db;
		$ip = $db->GetOne('SELECT `ip` FROM `'.prefix.'posts` WHERE `boardname` = '.$db->quote($_GET['board']).' AND `id` = '.$_GET['id']);
		echo $ip;
		die();
	}
	public static function RebuildAll() {
		global $db, $twig_data;
		$sucess = false;
		$board_core = new BoardCore();
		if (isset($_POST['run'])) {
			$time_start = $board_core->microtime_float();
			$twig_data['cache'] = 1;
			$data = $db->GetAll('SELECT * FROM `'.prefix.'boards`');
			foreach ($data as $line) {
				$board_core->Board($line['name']);
				$board_core->RefreshAll();
			}
			$total_time = $board_core->microtime_float() - $time_start;
			$end = round($total_time, 4);
			$twig_data['done'] = 1;
			$twig_data['message'] = '<font color="green">Twig cache successfully cleared in '.$end.' s';
		}
		AnonsabaCore::Output('/manage/site/rebuild_all.tpl', $twig_data);
	}
	public static function RebuildBoard() {
		global $db, $twig_data;
		$sucess = false;
		$twig_data['boards'] = $db->GetAll('SELECT * FROM `'.prefix.'boards` ORDER BY `name` ASC');
		$board_core = new BoardCore();
		if (isset($_POST['run'])) {
			$time_start = $board_core->microtime_float();
			$twig_data['cache'] = 1;
			$board_core->Board($_POST['board']);
			$board_core->RefreshAll();
			$total_time = $board_core->microtime_float() - $time_start;
			$end = round($total_time, 4);
			$twig_data['done'] = 1;
			$twig_data['message'] = '<font color="green">Twig cache successfully cleared in '.$end.' s';
		}
		AnonsabaCore::Output('/manage/site/rebuild_board.tpl', $twig_data);
	}
	public static function siteconfig() {
		global $db, $twig_data;
		if (isset($_POST['submit'])) {
			if (is_numeric($_POST['timgw']) && is_numeric($_POST['timgh']) && is_numeric($_POST['rimgw']) && is_numeric($_POST['rimgh'])) {
				$conf_names = array('sitename', 'slogan', 'irc', 'timgh', 'timgw', 'rimgh', 'rimgw', 'bm');
				$conf_values = array($_POST['sitename'], $_POST['slogan'], $_POST['irc'], $_POST['timgh'], $_POST['timgw'], $_POST['rimgh'], $_POST['rimgw'], $_POST['bm']);
				// Have to have two executes since the array starts at 0 and the for loop only counts down to 1
				$db->GetAll('UPDATE `'.prefix.'siteconfig` SET `config_value` ='.$db->quote($conf_values[0]).' WHERE `config_name` = '.$db->quote($conf_names[0]));
				for ($i = 8; --$i;) {
					$db->GetAll('UPDATE `'.prefix.'siteconfig` SET `config_value` = '.$db->quote($conf_values[$i]).' WHERE `config_name` = '.$db->quote($conf_names[$i]));
				}
				$twig_data['message'] = '<font color="green">Site configuration successfully updated!</font>';
			} else {
				$twig_data['message'] = '<font color="red">Width and Height must be numeric values!</font>';
			}
			$twig_data['sitename'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "sitename"');
			$twig_data['slogan'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "slogan"');
			$twig_data['verison'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "version"');
			$twig_data['irc'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "irc"');
			$twig_data['timgh'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "timgh"');
			$twig_data['timgw'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "timgw"');
			$twig_data['rimgh'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "rimgh"');
			$twig_data['rimgw'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "rimgw"');
			$twig_data['bm'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "bm"');
		} else {
			$twig_data['sitename'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "sitename"');
			$twig_data['slogan'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "slogan"');
			$twig_data['verison'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "version"');
			$twig_data['irc'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "irc"');
			$twig_data['timgh'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "timgh"');
			$twig_data['timgw'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "timgw"');
			$twig_data['rimgh'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "rimgh"');
			$twig_data['rimgw'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "rimgw"');
			$twig_data['bm'] = $db->GetOne('SELECT `config_value` FROM `'.prefix.'siteconfig` WHERE `config_name` = "bm"');
		}
		$board_core = new BoardCore();
		$data = $db->GetAll('SELECT * FROM `'.prefix.'boards`');
		foreach ($data as $line) {
			$board_core->Board($line['name']);
			$board_core->RefreshAll();
		}
		AnonsabaCore::Output('/manage/site/siteconfig.tpl', $twig_data);
	}
	public static function bans() {
		global $twig_data, $db;
		$twig_data['boards'] = $db->GetAll('SELECT * FROM `'.prefix.'boards`');
		$twig_data['show'] = isset($_GET['show']) ? $_GET['show'] : '';
		$twig_data['val'] = isset($_GET['val']) ? $_GET['val'] : '';
		$twig_data['other'] = isset($_GET['other']) ? $_GET['other'] : '';
		$twig_data['bm'] = isset($_GET['bm']) ? $_GET['bm'] : '';
		$twig_data['dbm'] = AnonsabaCore::GetConfigOption('bm');	
		$twig_data['do'] = isset($_GET['do']) ? $_GET['do'] : '';
		$twig_data['ip'] = isset($_GET['ip']) ? $_GET['ip'] : '';
		$twig_data['boardname'] = isset($_GET['boardname']) ? $_GET['boardname'] : '';
		$twig_data['bans'] = $db->GetAll('SELECT * FROM `'.prefix.'bans`');
		$twig_data['current'] = $_GET['side'];
		if ($_GET['act'] == 'del') {
			$db->Execute('DELETE FROM `'.prefix.'bans` WHERE `id` = '.$_GET['id']);
			$twig_data['msg'] = '<font color="green">Ban successfully delete</font>';
		}
		if (isset($_POST['submit'])) {
			if (isset($_POST['all'])) {
				$ban_boards = array('all');
			} else {
				$ban_boards = array();
				while (list($postkey, $postvalue) = each($_POST)) {
					if (substr($postkey, 0, 4) == "bans") {
						$ban_boards = array_merge($ban_boards, array(substr($postkey, 4)));
					}
				}
			}
			if ($_POST['appeal'] == 'yes') {
				$appeal = strtotime($_POST['appealin']);
			} else {
				$appeal = '0';
			}
			if ($_POST['ip'] != '') {
				if (implode('|', $ban_boards) != '') {
					if ($_POST['until'] != '') {
						$db->Execute('INSERT INTO `'.prefix.'bans` (`ip`, `boards`, `reason`,  `until`, `appeal`) VALUES ('.$db->quote($_POST['ip']).','.$db->quote(implode('|', $ban_boards)).', '.$db->quote($_POST['reason']).', '.strtotime($_POST['until']).', '.$appeal.')');
						if (isset($_GET['boardname']) && $_POST['bm'] != 'no') {
							$db->Execute('UPDATE `'.prefix.'posts` SET `banmessage` = '.$db->quote($_POST['bm1']).' WHERE `ip` = '.$db->quote($_GET['ip']).' AND `id` = '.$_GET['id'].' AND `boardname` = '.$db->quote($_GET['boardname']));
							$board_core = new BoardCore();
							$board_core->Board($_GET['boardname']);
							$board_core->RefreshAll();
						}
						$twig_data['msg'] = '<font color="green">'.$_POST['ip'].' successfully banned!</font>';
					} else {
						$twig_data['msg'] = '<font color="red">Please enter a ban duration.</font>';
					}
				} else {
					$twig_data['msg'] = '<font color="red">Please enter a board</font>';
				}
			} else {
				$twig_data['msg'] = '<font color="red">Please enter an IP to be banned!</font>';
			}
			AnonsabaCore::Output('/manage/moderation/bans.tpl', $twig_data);
		} else {
			$bans = $db->GetAll('SELECT * FROM `'.prefix.'bans`');
			$twig_data['bans'] = $bans;
			AnonsabaCore::Output('/manage/moderation/bans.tpl', $twig_data);
		}
	}
	public static function appeal() {
		global $twig_data, $db;
		if ($_GET['act'] == 'approve') {
			$db->Execute('DELETE FROM `'.prefix.'bans` WHERE `id` = '.$_GET['id']);
			$twig_data['msg'] = '<font color="green">Appeal successfully approved!</font>';
			$twig_data['appeals'] = $db->GetAll('SELECT * FROM `'.prefix.'bans` WHERE `appealed` = 1 AND `deny` = 0');
			AnonsabaCore::Output('/manage/moderation/appeal.tpl', $twig_data);
		} elseif ($_GET['act'] == 'deny') {
			$db->Execute('UPDATE `'.prefix.'bans` SET `deny` = 1 WHERE `id` = '.$_GET['id']);
			$twig_data['msg'] = '<font color="green">Appeal successfully denied!</font>';
			$twig_data['appeals'] = $db->GetAll('SELECT * FROM `'.prefix.'bans` WHERE `appealed` = 1 AND `deny` = 0');
			AnonsabaCore::Output('/manage/moderation/appeal.tpl', $twig_data);
		} else {
			$twig_data['appeals'] = $db->GetAll('SELECT * FROM `'.prefix.'bans` WHERE `appealed` = 1 AND `deny` = 0');
			AnonsabaCore::Output('/manage/moderation/appeal.tpl', $twig_data);
		}
	}
	public static function reports() {
		global $twig_data, $db;
		$twig_data['reports'] = $db->GetAll('SELECT * FROM `'.prefix.'posts` WHERE `report` = 1');
		if ($_GET['act'] == 'clear') {
			$db->Execute('UPDATE `'.prefix.'posts` SET `report` = 2 WHERE `id` = '.$_GET['id'].' AND `boardname` = '.$db->quote($_GET['board']));
			$twig_data['msg'] = '<font color="green">Report successfully cleared</font>';
			AnonsabaCore::Output('/manage/moderation/reports.tpl', $twig_data);
		} else {
			AnonsabaCore::Output('/manage/moderation/reports.tpl', $twig_data);
		}
	}
	public static function movethread() {
		global $twig_data, $db;
		$twig_data['boards'] = $db->GetAll('SELECT * FROM `'.prefix.'boards`');
		if (isset($_POST['submit'])) {
			if ($_POST['id'] == '') {
				$twig_data['message'] = '<font color="red">Please enter a Thread ID!</font>';
			} elseif ($_POST['board'] == $_POST['newboard']) {
				$twig_data['message'] = '<font color="red">Thread can not be moved to the same board!</font>';
			} elseif (!$db->GetAll('SELECT * FROM `'.prefix.'posts` WHERE `boardname` = '.$db->quote($_POST['board']).' AND `id` = '.$_POST['id'])) {
				$twig_data['message'] = '<font color="red">Thread doesn\'t exist!</font>';
			} else {
				$newid = $db->GetOne('SELECT COALESCE(MAX(id),0) + 1 FROM `'.prefix.'posts` WHERE `boardname` = '.$db->quote($_POST['newboard']));
				$from = fullpath . $_POST['board'] . '/res/'. $_POST['id'] . '.html';
				@unlink($from);
				$image = $db->GetOne('SELECT `file` FROM `'.prefix.'files` WHERE `board` = '.$db->quote($_POST['board']).' AND `id` = '.$_POST['id']);
				$filetype = $db->GetOne('SELECT `type` FROM `'.prefix.'files` WHERE `board` = '.$db->quote($_POST['board']).' AND `id` = '.$_POST['id']);
				$from_pic = fullpath . $_POST['board'] . '/src/'. $image . $filetype;
				$to_pic = fullpath . $_POST['newboard'] . '/src/'. $image . $filetype;
				@rename($from_pic, $to_pic);
				@unlink($from_pic);
				$db->Execute('UPDATE `'.prefix.'posts` SET `id` = '.$newid.', `boardname` = '.$db->quote($_POST['newboard']).' WHERE `id` = '.$_POST['id'].' AND `boardname` = '.$db->quote($_POST['board']));
				$db->Execute('UPDATE `'.prefix.'files` SET `id` = '.$newid.', `board` = '.$db->quote($_POST['newboard']).' WHERE `id` = '.$_POST['id'].' AND `board` = '.$db->quote($_POST['board']));
				$posts = $db->GetAll('SELECT * FROM `'.prefix.'posts` WHERE `parent` = '.$_POST['id'].' AND `boardname` = '.$db->quote($_POST['board']));
				foreach ($posts as $post) {
					$files = $db->GetAll('SELECT `file`, `type` FROM `'.prefix.'files` WHERE `board` = '.$db->quote($_POST['board']).' AND `id` = '.$post['id']);
					$rimage = $db->GetOne('SELECT `file` FROM `'.prefix.'files` WHERE `board` = '.$db->quote($_POST['board']).' AND `id` = '.$post['id']);
					$rtype = $db->GetOne('SELECT `type` FROM `'.prefix.'files` WHERE `board` = '.$db->quote($_POST['board']).' AND `id` = '.$post['id']);
					$from_pic = fullpath. $_POST['board'] . '/src/'. $rimage . $rtype;
					$to_pic = fullpath . $_POST['newboard'] . '/src/'. $rimage . $rtype;
					@rename($from_pic, $to_pic);
					@unlink($from_pic);
					$insert_id = $db->GetOne('SELECT COALESCE(MAX(id),0) + 1 FROM `'.prefix.'posts` WHERE `boardname` = '.$db->quote($_POST['newboard']));
					$db->Execute('UPDATE `'.prefix.'posts` SET `id` = '.$insert_id.', `boardname` = '.$db->quote($_POST['newboard']).' WHERE `boardname` = '.$db->quote($_POST['board']).' AND `id` = '.$post['id']);
					$db->Execute('UPDATE `'.prefix.'files` SET `id` = '.$insert_id.', `board` = '.$db->quote($_POST['newboard']).' WHERE `board` = '.$db->quote($_POST['board']).' AND `id` = '.$post['id']);
					$db->Execute('UPDATE `'.prefix.'posts` SET `parent` = '.$newid.' WHERE `boardname` = '.$db->quote($_POST['newboard']).' AND `id` = '.$insert_id);
				}
				$board_core = new BoardCore();
				$data = $db->GetAll('SELECT * FROM `'.prefix.'boards`');
				foreach ($data as $line) {
					$board_core->Board($line['name']);
					$board_core->RefreshAll();
				}
				$twig_data['message'] = '<font color="green">Thread Successfully moved!</font>';
			}
		}
		AnonsabaCore::Output('/manage/board/movethread.tpl', $twig_data);
	}
	public static function ads() {
		global $db, $twig_data;
		if ($_GET['do'] == 'del') {
			$db->Execute('DELETE FROM `'.prefix.'ads` WHERE `id` = '.$_GET['id']);
			$twig_data['message'] = '<font color="green">Ad successfully deleted!</font>';
			$twig_data['ads'] = $db->GetAll('SELECT * FROM `'.prefix.'ads`');
		}
		if (isset($_POST['submit'])) {
			$adcheck = $db->GetAll('SELECT * FROM `'.prefix.'ads` WHERE `type` = '.$db->quote($_POST['type']));
			if (!$adcheck) {
				$db->Execute('INSERT INTO `'.prefix.'ads` (`url`, `type`, `w`, `h`) VALUES ('.$db->quote($_POST['url']).', '.$db->quote($_POST['type']).', '.$_POST['w'].', '.$_POST['h'].')');
				$twig_data['message'] = '<font color="green">Ad successfully added!</font>';
			} else {
				$twig_data['message'] = '<font color="red">An Ad for '.$_POST['type'].' already exist!</font>';
			}
			$twig_data['ads'] = $db->GetAll('SELECT * FROM `'.prefix.'ads`');
		} else {
			$twig_data['ads'] = $db->GetAll('SELECT * FROM `'.prefix.'ads`');
		}
		AnonsabaCore::Output('/manage/board/ads.tpl', $twig_data);
	}
	public static function ExpireBans() {
		global $db;
		$time = time();
		$bans = $db->GetAll('SELECT * FROM `'.prefix.'bans` WHERE `until` <= '.$time);
		if ($bans) {
			foreach($bans as $line) {
				$db->Execute('INSERT INTO `'.prefix.'expiredbans` (`ip`, `reason`) VALUES ('.$db->quote($line['ip']).', '.$db->quote($line['reason']).')');
			}
			$db->Execute('DELETE FROM `'.prefix.'bans` WHERE `until` <= '.$time);
		}
	}
	public static function memory() {
		return substr(memory_get_peak_usage() / 1024 / 1024, 0, 4);
	}
}
