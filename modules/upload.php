<?php
class Upload {
	var $files			= array();
	var $file_location		= '';
	var $file_thumb_location	= '';
	var $file_thumb_cat_location	= '';
	var $isvideo			= false;
	function HandleUpload() {
		global $db, $board_core;

		$imagefile_name = isset($_FILES['imagefile']) ? $_FILES['imagefile']['name'][0] : '';
		if ($imagefile_name != '') {
			if (count($_FILES['imagefile']['name']) > $board_core->board['fileperpost']){
				AnonsabaCore::Error('Please select only '. $board_core->board['fileperpost'].' file(s) to upload.');
			}
			for($i=0;$i<$board_core->board['fileperpost'];$i++) {
				if (!$_FILES['imagefile']['name'][$i]) {
					// Previous file was the last uploaded.
					break;
				} else {
					if ($_FILES['imagefile']['size'][$i] > $board_core->board['imagesize']) {
						AnonsabaCore::Error('Please make sure your file(s) is smaller than %dB', $board_core->board['imagesize']);
					}
					switch ($_FILES['imagefile']['error'][$i]) {
						case UPLOAD_ERR_OK:
							break;
						case UPLOAD_ERR_INI_SIZE:
							AnonsabaCore::Error('The uploaded file(s) exceeds the upload_max_filesize directive '.ini_get('upload_max_filesize').'B in php.ini.');
							break;
						case UPLOAD_ERR_FORM_SIZE:
							AnonsabaCore::Error('Please make sure your file(s) is smaller than'.$board_core->board['imagesize']);
							break;
						case UPLOAD_ERR_PARTIAL:
							AnonsabaCore::Error('The uploaded file(s) was only partially uploaded.');
							break;
						case UPLOAD_ERR_NO_FILE:
							AnonsabaCore::Error('No file(s) was uploaded.');
							break;
						case UPLOAD_ERR_NO_TMP_DIR:
							AnonsabaCore::Error('Missing a temporary folder.');
							break;
						case UPLOAD_ERR_CANT_WRITE:
							AnonsabaCore::Error('Failed to write file to disk');
							break;
						default:
							AnonsabaCore::Error('Unknown File Error');
					}
					$this->files[$i]['file_type'] = preg_replace('/.*(\..+)/','\1',$_FILES['imagefile']['name'][$i]);
					if ($this->files[$i]['file_type'] == '.jpeg') {
					/* Fix for the rarely used 4-char format */
						$this->files[$i]['file_type'] = '.jpg';
					}
					$allowed = $db->GetAll('SELECT * FROM `'.prefix.'board_filetypes` WHERE `id` = '.$board_core->board['id']);
					foreach ($allowed as $allow) {
						$allowedfiles = $db->GetAll('SELECT `name` FROM `'.prefix.'filetypes` WHERE `id` = '.$allow['fileid']);
						foreach ($allowedfiles as $af) {
							if (!in_array($this->files[$i]['file_type'], '.'.$af['name'])) {
								AnonsabaCore::Error('Sorry, that filetype is not allowed on this board.');
							}
						}
					}	
					$pass = true;
					if (!is_file($_FILES['imagefile']['tmp_name'][$i]) || !is_readable($_FILES['imagefile']['tmp_name'][$i])) {
						$pass = false;
					} else {
						if ($this->files[$i]['file_type'] == '.jpg' || $this->files[$i]['file_type'] == '.gif' || $this->files[$i]['file_type'] == '.png') {
							if (!@getimagesize($_FILES['imagefile']['tmp_name'][$i])) {
								$pass = false;
							}
						}
					}
					if (!$pass) {
						AnonsabaCore::Error('File transfer failure. Please go back and try again.');
					}
					$this->files[$i]['file_name'] = substr(htmlspecialchars(preg_replace('/(.*)\..+/','\1',$_FILES['imagefile']['name'][$i]), ENT_QUOTES), 0, 50);
					$this->files[$i]['file_name'] = str_replace('.','_',$this->files[$i]['file_name']);
					$this->files[$i]['original_file_name'] = $this->files[$i]['file_name'];
					$this->files[$i]['file_md5'] = md5_file($_FILES['imagefile']['tmp_name'][$i]);
					$exists = $db->GetOne('SELECT `id` from `'.prefix.'files` WHERE `md5` ='.$db->quote($this->files[$i]['file_md5']));
					if ($exists) {
						$exists_file = $db->GetOne('SELECT `id` from `'.prefix.'files` WHERE `md5` ='.$db->quote($this->files[$i]['file_md5']));
						$exists_threadnum = $db->GetOne('SELECT `id` FROM `'.prefix.'posts` WHERE `id` = '.$exists_file.' AND boardname = '.$db->quote($board_core->board['name']));
						$exists_parent = $db->GetOne('SELECT `parent` FROM `'.prefix.'posts` WHERE `id` = '.$exists_file.' AND boardname = '.$db->quote($board_core->board['name']));
						if ($exists_parent == 0) {
							$exists_parent = $db->GetOne('SELECT `id` FROM `'.prefix.'posts` WHERE `id` = '.$exists_file.' AND boardname = '.$db->quote($board_core->board['name']));
						}
						AnonsabaCore::Error('Duplicate file entry detected.', 'Already posted '.'<a href="' . url . $board_core->board['name'] . '/res/' . $exists_parent .'.html#' . $exists_threadnum . '">here</a>');
					}
					$imageDim = getimagesize($_FILES['imagefile']['tmp_name'][$i]);
					$this->files[$i]['imgWidth'] = $imageDim[0];
					$this->files[$i]['imgHeight'] = $imageDim[1];
					$this->files[$i]['file_type'] = strtolower($this->files[$i]['file_type']);
					$this->files[$i]['file_size'] = $_FILES['imagefile']['size'][$i];
					$this->files[$i]['file_name'] = time() . mt_rand(1, 99);
					$this->file_location[$i] = fullpath . $board_core->board['name'] . '/src/' . $this->files[$i]['file_name'] . $this->files[$i]['file_type'];
					$this->file_thumb_location[$i] = fullpath . $board_core->board['name'] . '/thumb/' . $this->files[$i]['file_name'] . 's' . $this->files[$i]['file_type'];
					if (!move_uploaded_file($_FILES['imagefile']['tmp_name'][$i], $this->file_location[$i])) {
						AnonsabaCore::Error('Could not copy uploaded image(s).');
					}
					chmod($this->file_location[$i], 0755);
					$this->files[$i]['file_name'] = htmlspecialchars_decode($this->files[$i]['file_name'], ENT_QUOTES);
					$this->files[$i]['file_name'] = stripslashes($this->files[$i]['file_name']);
					$this->files[$i]['file_name'] = str_replace("\x80", " ", $this->files[$i]['file_name']);					
					$this->files[$i]['file_name'] = str_replace(' ', '_', $this->files[$i]['file_name']);
					$this->files[$i]['file_name'] = str_replace('#', '(number)', $this->files[$i]['file_name']);
					$this->files[$i]['file_name'] = str_replace('@', '(at)', $this->files[$i]['file_name']);
					$this->files[$i]['file_name'] = str_replace('/', '(fwslash)', $this->files[$i]['file_name']);
					$this->files[$i]['file_name'] = str_replace('\\', '(bkslash)', $this->files[$i]['file_name']);
					$this->file_location[$i] = fullpath . $board_core->board['name'] . '/src/' . $this->files[$i]['file_name'] . $this->files[$i]['file_type'];

					if ($filetype_required_mime != '') {
						if (mime_content_type($this->file_location[$i]) != $filetype_required_mime) {
							unlink($this->file_location[$i]);
							AnonsabaCore::Error('Invalid MIME type for this filetype.');
						}
					}
					if ($_FILES['imagefile']['size'][$i] == filesize($this->file_location[$i])) {
						$imageused = true;
					} else {
						AnonsabaCore::Error('File transfer failure. Please go back and try again.');
					}
					$this->files[$i]['file_is_special'] = true;
				}
			}
		}
	}
}
