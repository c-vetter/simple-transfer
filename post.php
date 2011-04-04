<?php
/**
 * rpTransfer - a simple web app for asynchronously transferring single files
 * Copyright (c) 2010 rasenplanscher [ code.rasenplanscher.info ]
 */

# generate the id for the new file
$id = new_id();

$file =& $_FILES['file'];
$error_code = $file['error'];

db_append_log($id, 'start processing upload request');

send_xhtml_header();
yield('prefix');

if (empty($file)) {
	# the file was too large, thus the web server aborted receiving the post data
	db_append_log($id, 'error during upload: the file was too large for the server');
	$error_code = 'FILE_TOO_LARGE';
	$data = array('size' => format_size($max_filesize));
} else switch ($error_code) {
	case UPLOAD_ERR_OK:
		# upload succeeded
		db_append_log($id, 'upload successful');
		
		$metadata = array();
		$metadata['name'] =& $file['name'];
		if($username)
			$metadata['uploader'] =& $username;
		
		if ($file['type'])
			$metadata['type'] =& $file['type'];
		if (move_uploaded_file($file['tmp_name'], 'files/'.$id))
			db_append_log($id, 'moved file');
		else {
			db_append_log($id, 'could not save file');
			$error_code = 'FILE_NOT_SAVED';
		}
		
		$metadata['expire'] = time() + $file_ttl;
		$metadata['size'] = filesize('files/'.$id);
		$metadata['virgin'] = true;
		
		db_set_metadata($id, $metadata);
		db_append_log($id, 'metadata saved');
		
		$data = array('uri' => uri('download', $id));
	break;
	
	case UPLOAD_ERR_INI_SIZE:
		# the uploaded file exceeds the upload_max_filesize directive
		db_append_log($id, 'error during upload: the file was too large for the script');
		$data = array('size' => format_size($max_filesize));
	break;
	
	case UPLOAD_ERR_FORM_SIZE:
		# the uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form
		db_append_log($id, 'error during upload: the file was too large, according to the form');
		$data = array('size' => format_size($max_filesize));
	break;
	
	case UPLOAD_ERR_PARTIAL:
		# the file was only partially uploaded
		db_append_log($id, 'error during upload: the file was only partially uploaded');
	break;
	
	case UPLOAD_ERR_NO_FILE:
		# no file was uploaded
		db_append_log($id, 'error during upload: no file was uploaded');
	break;
	
	case UPLOAD_ERR_NO_TMP_DIR:
		# missing a temporary folder
		db_append_log($id, 'error during upload: themissing a temporary folder');
	break;
	
	case UPLOAD_ERR_CANT_WRITE:
		# failed to write file to disk
		db_append_log($id, 'error during upload: failed to write file to disk');
	break;
	
	case UPLOAD_ERR_EXTENSION:
		# file upload stopped by extension
		db_append_log($id, 'error during upload: file upload stopped by extension');
	break;
	
	default:
		# an unknown error has occurred
		db_append_log($id, 'unknown error during upload -- should be looked into asap');
		$error_code = 'UNKNOWN_ERROR';
}

# print a message according to the level of success or failure
$message = $_configuration['upload result'][$post_errors[$error_code]];
if (intval($message))
	error(intval($message), NULL, $data);
else
	yield($message, $data);

yield('suffix');

db_append_log($id, 'processing of upload request complete');
