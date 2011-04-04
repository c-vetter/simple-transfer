<?php
/**
 * rpTransfer - a simple web app for asynchronously transferring single files
 * Copyright (c) 2010 rasenplanscher [ code.rasenplanscher.info ]
 */

if (empty($id)) {
	# the user wants to send a file
	send_xhtml_header();
	yield('prefix');
	yield('upload form', array('size' => format_size($max_filesize)));
	yield('suffix');
	
} else if (preg_match("/^[0-9a-f]{{$id_length}}$/", $id)) {
	# the user wants to retrieve a file
	
	db_append_log($id, 'start processing download request');
	
	db_append_log($id, 'client identification is '.$_SERVER['HTTP_USER_AGENT']);
	
	if (!db_exists($id, 'metadata'))
		file_error($id, 'metadata missing, aborting', 404);
	db_append_log($id, 'metadata exists');
	
	if (!($metadata = db_get_metadata($id)))
		file_error($id, 'error loading metadata, aborting', 500);
	db_append_log($id, 'metadata retrieval successful');
	
	if (time() > intval($metadata['expire']))
		file_error($id, 'file has expired, aborting', 404);
	db_append_log($id, 'file still valid');
	
	if (!db_exists($id, 'file'))
		file_error($id, 'file missing', 404);
	db_append_log($id, 'file exists');
	
	if (filesize('files/'.$id) != $metadata['size'])
		file_error($id, 'file was changed, aborting', 500);
	db_append_log($id, 'file untempered');
	
	db_append_log($id, 'start sending headers');
	header('HTTP/1.1 200 OK');
	if (
	 (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') === false)
	 &&
	 $metadata['type']
	)
		header('Content-Type: '.$metadata['type']);
	header('Content-Length: '.$metadata['size']);
	header(sprintf('Content-Disposition: attachment; filename="%s"'
		, $metadata['name']
	));
	db_append_log($id, 'headers sent');
	
	db_append_log($id, 'start sending file');
	readfile('files/'.$id);
	db_append_log($id, 'file sent');
	
	if ($metadata['virgin']) {
		$metadata['virgin'] = false;
		db_set_metadata($id, $metadata);
		db_append_log($id, 'removed virgin status from file');
	}
	
	db_append_log($id, 'processing of download request complete');
	
} else switch($id) {
	case 'list':
		# client wants a list of all valid files and is authorized to do so
		if ($list_allowed) {
			send_xhtml_header();
			yield('prefix');
			yield('list prefix');
			
			# remove old files
			db_purge();
			
			# retrieve a list of all remaining files
			$files = db_list();
			
			# show file data to client
			while($file = each($files)) {
				$file = $file['value'];
				yield('list file', array(
					'uri' => uri('download', $file['id']),
					'name' => $file['name'],
					'uploader' => $file['uploader'] ?
						'['.$file['uploader'].']':
						'',
					'type' => $file['type'],
					'expire' => date('Y-m-d  H:i:s', $file['expire']),
					'size' => preg_replace(
						'/([0-9.]+)(.*)/',
						'\1<span class="unit">\2</span>',
						format_size($file['size'], 1)
					),
					'classes' => (
						$file['expire'] < time() ? 'expired ' : ''
					).(
						$file['virgin'] ? 'virgin' : ''
					)
				));
			}
			
			yield('list suffix');
			yield('suffix');
		} else
			error(403);
	break;
	default:
		# the client requested something that is not here
		error(404);
}
