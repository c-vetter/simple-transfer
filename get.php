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
	
	if (
		$_SERVER['HTTP_RANGE']
		# the user only wants a part of the file
		and
		preg_match(
			'/\A\s*bytes=([\-0-9,]+)\s*\Z/i',
			$_SERVER['HTTP_RANGE'],
			$ranges
		)
		# the ranges given look ok at first glance
		and
		preg_match_all(
			'/(\d*)-(\d*)(?:,|$)/',
			$ranges[1],
			$ranges,
			PREG_SET_ORDER
		)
		# the ranges given look ok at second glance
		and
		!in_array(
			NULL,
			$ranges = array_map(
				function($range){
					global $metadata;
					if ($range[1] > $range[2] and $range[2] != '')
						# range is syntactically invalid
						return NULL;
					return array(
						# no first byte given means start at the beginning
						$range[1] == ''
						? 0
						: (int) $range[1]
						,
						# no last byte given means don't stop before the end
						$range[2] == ''
						? $metadata['size'] - 1
						: min((int) $range[2], $metadata['size'] - 1) 
					);
				},
				$ranges
			)
		)
		# the ranges given are syntactically valid
		
		# there is no error message if the Range header is syntactically invalid
		# because the HTTP standard says syntactically invalid Range headers should be ignored
	) {
		db_append_log($id, 'request for range');
		# get the first valid range
		while ($range = array_shift($ranges)) {
			if($range[0] > $metadata['size'])
				$range = NULL;
			else
				break;
		}
		
		# bail if no valid ranges were given
		if ($range == NULL) {
			# also, tell the client how much data is available
			header('Content-Range: */'.$metadata['size']);
			error(416, NULL, array('maxrange' => $metadata['size']-1));
		}
		db_append_log($id, "request contains valid range (${range[0]}-${range[1]})");
		
		# if the requested range is equal to the file range,
		# this request need not be considered a range request at all
		if ($range[0] == 0 and $range[1] == $metadata['size'])
			$range = NULL;
	}
	
	db_append_log($id,
		$range
		? "will respond with partial file (range ${range[0]}-${range[1]}/${metadata['size']})"
		: 'will respond with complete file'
	);
	
	db_append_log($id, 'start sending headers');
	if ($range) {
		header('HTTP/1.1 206 Partial Content');
		header("Content-Range: ${range[0]}-${range[1]}/${metadata['size']}");
		header('Content-Length: '.($range[1] - $range[0] + 1));
	} else {
		header('HTTP/1.1 200 OK');
		header('Content-Length: '.$metadata['size']);
	}
	header('Accept-Ranges: bytes');
	if (
	 (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') === false)
	 and
	 $metadata['type']
	)
		header('Content-Type: '.$metadata['type']);
	header(sprintf('Content-Disposition: attachment; filename="%s"'
		, $metadata['name']
	));
	ob_end_clean();
	flush();
	db_append_log($id, 'headers sent');
	
	list($start, $end) = $range ?
		array($range[0], $range[1]+1):
		array(0, $metadata['size']);
	$chunk = 8192;
	db_append_log($id, 'determined transfer boundaries');
	$file = fopen('files/'.$id, 'rb');
	fseek($file, $start);
	db_append_log($id, 'opened file for reading');
	while(ftell($file) + $chunk < $end) {
		echo fread($file, $chunk);
	}
	echo fread($file, $end - ftell($file));
	db_append_log($id, 'sent all requested data');
	fclose($file);
	db_append_log($id, 'closed file');
	
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
