<?php
//This file is called whenever someone attempts to make a post on any board
//Call all the required files for post.php to work properly
require realpath(dirname(_DIR_)).'/config/config.php';
require fullpath.'modules/anonsaba.php';
require fullpath.'modules/board.php';
require fullpath.'modules/upload.php';
require fullpath.'modules/files.php';

$data = $db->GetAll('SELECT * FROM `'.prefix.'boards` WHERE `name` = '.$db->quote($_POST['board']));
$posts = $_POST['post'];
if (isset($_POST['delpost'])) {
	foreach ($posts as $delpost) {
		if ($_POST['postpassword'] == $db->GetOne('SELECT `password` FROM `'.prefix.'posts` WHERE `boardname` = '.$db->quote($_POST['board']).' AND `id` = '.$delpost)) {
			$db->Execute('DELETE FROM `'.prefix.'posts` WHERE `id` = '.$delpost.' AND `boardname` = '.$db->quote($_POST['board']));
		}
	}
	$board_core = new BoardCore();
	$board_core->Board($_POST['board']);
	$board_core->RefreshAll();
	header("Location: ".url.$_POST['board']);
}
if (isset($_POST['reportpost'])) {
	foreach ($posts as $post) {
		$reportcheck = $db->GetAll('SELECT * FROM `'.prefix.'posts` WHERE `id` = '.$post.' AND `boardname` = '.$db->quote($_POST['board']));
		foreach ($reportcheck as $line) {
			if ($line['report'] == 1) {
				echo '<link rel="stylesheet" type="text/css" href="'.url.'pages/css/futaba.css"><h2>This post is currently being validated';
			} elseif ($line['report'] == 2) {
				echo '<link rel="stylesheet" type="text/css" href="'.url.'pages/css/futaba.css"><h2>This post was already reported and cleared';
			} else {
				$db->Execute('UPDATE `'.prefix.'posts` SET `report` = 1, `reportmsg` = '.$db->quote($_POST['reportreason']).' WHERE `id` = '.$post.' AND `boardname` = '.$db->quote($_POST['board']));
				echo '<link rel="stylesheet" type="text/css" href="'.url.'pages/css/futaba.css"><h2>Post successfully reported';
			}
		}
	}
}
if ($_GET['action'] == 'appeal') {
	$db->Execute('UPDATE `'.prefix.'bans` SET `appealmsg` = '.$db->quote($_POST['appealmsg']).', `appealed` = 1 WHERE `id` = '.$_GET['id']);
	AnonsabaCore::Banned($_GET['board'], $_GET['ip'], 1);
}
if (isset($_POST['submit'])) {
	foreach ($data as $line) {
		if ($line['locked'] == 1) {
			AnonsabaCore::Error('ERROR', 'This board is currently locked');
		} elseif ($_POST['message'] == '' && $_FILES['imagefile']['error'][0] == '4' && !$_POST['nofile']) {
			AnonsabaCore::Error('Please enter', 'A message or a file no blank posts');
		} else {
			$level = 0;
			$sticky = 0;
			$lock = 0;
			$bumped = 0;
			$immune = 0;
			$rw = 0;
			if ($_POST['modpassword']!= '' && $db->GetAll('SELECT COUNT(*) FROM `'.prefix.'staff` WHERE `sessionid` = '.$db->quote($_POST['modpassword'])) > 0) {
				if (isset($_POST['displaystaffstatus'])) {
					$levels = $db->GetAll('SELECT `level` FROM `'.prefix.'staff` WHERE `sessionid` = '.$db->quote($_POST['modpassword']));
					if ($levels = 'admin') {
						$level = 1;
					} elseif ($levels = 'supermod') {
						$level = 2;
					} elseif ($levels = 'mod') {
						$level = 3;
					} elseif ($levels = 'VIP') {
						$level = 4;
					}
				}
				if (isset($_POST['sticky']) && $_POST['replythread'] == 0) {
					$sticky = 1;
				} elseif (isset($_POST['sticky'])) {
					$db->Execute('UPDATE `posts` SET `sticky` = 1 WHERE `id` = '.$_POST['replythread'].' AND `boardname` = '.$db->quote($_POST['board']));
				}
				if (isset($_POST['lock']) && $_POST['replythread'] == 0) {
					$lock = 1;
				} elseif (isset($_POST['lock'])) {
					$db->Execute('UPDATE `posts` SET `lock` = 1 WHERE `id` = '.$_POST['replythread'].' AND `boardname` = '.$db->quote($_POST['board']));
				}
				if (isset($_POST['rawhtml'])) {
					$rw = 1;
				}
				$immune = 1;
				if (isset($_POST['deletepost'])) {
					$db->Execute('DELETE FROM `'.prefix.'posts` WHERE `id` = '.$_POST['post'].' AND `boardname` = '.$db->quote($_POST['board']));
				}	
			}
			if ($_POST['name'] != '') {
				setcookie("name", $_POST['name'], time() + 3600*6, '/', cookies);
			} elseif ($_POST['em'] != '') {
				setcookie("em", $_POST['em'], time() + 3600*6, '/', cookies);
			}
			if ($_POST['replythread'] != 0) {
				$db->Execute('UPDATE `'.prefix.'posts` SET `bumped` = '.time().' WHERE `boardname` = '.$db->quote($_POST['board']).' AND `id` = '.$_POST['replythread']);
			} else {
				$bumped = time();
			}		
			if ($_POST['replythread'] != 0) {
				$thread = $db->GetOne('SELECT `lock` FROM `'.prefix.'posts` WHERE `boardname` = '.$db->quote($_POST['board']).' AND `id` = '.$_POST['replythread']);
				if ($thread == 1 && $immune == 0) {
					AnonsabaCore::Error('This thread is currently locked and cannot be replied too');
				}
			}
			if ($_POST['password'] == '') {
				$password = $_COOKIE['postpassword'];
			} else {
				$password = $_POST['password'];
			}
			if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
				$ipaddy = $_SERVER['HTTP_CLIENT_IP'];
			} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { 
				$ipaddy = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ipaddy = $_SERVER['REMOTE_ADDR'];
			}
			$bancheck = $db->GetOne('SELECT `boards` FROM `'.prefix.'bans` WHERE `ip` = '.$db->quote($ipaddy));
			$boards = explode('|', $bancheck);
			if (in_array($_POST['board'], $boards) || $boards[0] == 'all') {
				AnonsabaCore::Banned($_POST['board'], $ipaddy);
			}
			if ($_FILES['imagefile']['error'][0] == '4' && !$_POST['nofile'] && $_POST['replythread'] == 0) {
				AnonsabaCore::Error('Sorry', 'Please select a file to upload');
			}
			$id = $db->GetOne('SELECT COALESCE(MAX(id),0) + 1 FROM `'.prefix.'posts` WHERE `boardname` = '.$db->quote($_POST['board']));
			$newpostmsg = AnonsabaCore::ParsePost($_POST['message'], $_POST['board']);
			$db->Execute('INSERT INTO `'.prefix.'posts` (`id`, `name`, `email`, `subject`, `message`, `password`, `level`, `parent`, `sticky`, `lock`, `rw`, `ip`, `ipid`, `boardname`, `time`, `bumped`) VALUES ('.$db->quote($id).', '.$db->quote(AnonsabaCore::trip($_POST['name'])).', '.$db->quote($_POST['em']).', '.$db->quote($_POST['subject']).', '.$db->quote($newpostmsg).', '.$db->quote($password).', '.$level.', '.$db->quote($_POST['replythread']).', '.$sticky.', '.$lock.', '.$rw.', '.$db->quote($ipaddy).', '.$db->quote(AnonsabaCore::Encrypt($ipaddy)).', '.$db->quote($_POST['board']).', '.time().', '.$bumped.')');
			$board_core = new BoardCore();
			$board_core->Board($_POST['board']);
			$fileid = $id;
			$fileboard = $_POST['board'];
			if ($_FILES['imagefile']['error'][0] != '4') {
				$upload = new Upload();
				$upload->HandleUpload();
				$db->Execute('INSERT INTO `'.prefix.'files` (`id`, `board`, `file`, `md5`, `type`, `original`, `size`) VALUES ('.$fileid.', '.$db->quote($fileboard).', '.$db->quote($upload->files[0]['file_name']).', '.$db->quote($upload->files[0]['file_md5']).', '.$db->quote($upload->files[0]['file_type']).', '.$db->quote($upload->files[0]['original_file_name']).', '.$upload->files[0]['file_size'].')');
			}
			$board_core->RefreshAll();
			if ($_POST['replythread'] == 0) {
				header("Location: ".url.$_POST['board']);
			} else {
				header("Location: ".url.$_POST['board'].'/res/'.$_POST['replythread'].'.html');
			}
		}
	}
}
