<?php
/**
 * rpTransfer - a simple web app for asynchronously transferring single files
 * Copyright (c) 2010 rasenplanscher [ code.rasenplanscher.info ]
 */



/**
 * Present a size (in bytes) as a human-readable value
 * 
 * @param   Int         size, in bytes
 * @param   Int         number of digits after the decimal point, defaults to zero
 * 
 * @return  String
 * 
 * Precision is ignored for sizes smaller than MB.
 * 
 * Based on
 * 	http://www.php.net/manual/en/function.filesize.php#99333
 * which in turn is based on
 * 	http://www.php.net/manual/en/function.filesize.php#98981
 */
function format_size($size, $precision=0) {
	# in practice, MB will not be exceeded
	$units = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
	if ($size == 0)
		return 'n/a';
	else {
		$scale = floor(log($size, 1024));
		return round($size/pow(1024, $scale), $scale > 1 ? $precision : 0).$units[$scale];
	}
}

/**
 * @param   String      duration, using the units d for days, h for hours, and m for minutes
 * 
 * @return  Integer     time to live for files, in seconds
 */
function time2seconds($time) {
	# find all occurrences of 'Nu', where N is an unsigned number and u is one of 'd', 'h', 'm'
	preg_match_all('/[0-9]+[dhm]/', $time, $seconds);
	
	# convert all found time intervals into seconds
	foreach($seconds[0] as $i => $s) {
		switch(substr($s, -1)) {
			case 'd';
				$s = intval($s) * 24;
			case 'h';
				$s = intval($s) * 60;
			case 'm';
				$s = intval($s) * 60;
		}
		$seconds[$i] = $s;
	}
	
	# return the number of seconds that make up the sum of all found time intervals
	return array_sum($seconds);
}



/**
 * generate a new, unique id
 * 
 * @return  String      hexadecimal number
 */
function new_id() {
	global $id_length;
	
	# generate a random 40-character hexadecimal number
	# and shorten it the the length given in the configuration
	$id = substr(sha1(microtime()), 0, $id_length);
	
	# check whether the id is already in use
	# and retry if it is
	if (db_exists($id))
		return new_id();
	else
		return $id;
}

/**
 * generate a URI
 * 
 * @param	String      tells the purpose of the uri to be determined
 * @param	String      hexadecimal number identifying a set of files previously uploaded, optional
 * 
 * @return  String      the desired uri
 */
function uri($for, $id=NULL) {
	global $basepath;
	
	# determine the base uri for the application
	$uri = sprintf('http://%s%s'
		, $_SERVER['HTTP_HOST']
		, $basepath
	);
	
	# return URIs appropriate for the given purpose
	switch($for) {
		case 'application':
			return $uri;
		case 'download':
			if($id)
				return $uri.$id;
			else
				return false;
		case 'list':
			return $uri.'list';
	}
}




/**
 * aborts with an error message
 * 
 * @param   Mixed       the error message as a string or an HTTP error code as an Integer
 * @param   Array       list of snippets to be displayed in addition to main error message, optional
 * @param   Array       list of placeholders and their substitutions, optional
 * 
 * @return  Void
 */
function error($error, $snippets=array(), $substitutions=NULL) {
	# discard all previous output
	ob_clean();
	
	# terminate execution with the given error message
	if (is_int($error)) {
		# numerical error codes get a nice output
		send_xhtml_header($error);
		
		yield('prefix');
		
		# show the main error message
		yield("error $error", $substitutions);
		# yield additional snippets after the error message
		foreach($snippets as $snippet)
			yield($snippet, $substitutions);
		
		yield('suffix');
		
		exit;
	} else {
		header('Content-Type: text/plain; charset=utf-8', NULL, 500);
		exit('error: '.$error);
	}
}

/**
 * aborts with an error message, after logging a file related error
 * 
 * @param   String      hexadecimal number identifying a set of files previously uploaded, optional
 * @param   String      the error message
 * @param   Integer     HTTP error code
 * 
 * @return  Void
 */
function file_error($id, $message, $code) {
	# write to file log
	db_append_log($id, $message);
	
	# terminate execution with the given error code
	error($code);
}

/**
 * send the correct XHTML content header for all web browsers declaring acceptance
 * 
 * @param   Integer     HTTP response code, optional
 * 
 * @return  Void
 */
function send_xhtml_header($status=NULL) {
	if (strpos($_SERVER['HTTP_ACCEPT'], 'application/xhtml+xml') === false)
		header('Content-Type: text/html; charset=utf-8', true, $status);
	else
		header('Content-Type: application/xhtml+xml; charset=utf-8', true, $status);
}

/**
 * print a snippet to the screen
 * 
 * @param   String      the snippet's name
 * @param   Array       list of placeholders and their substitutions, optional
 * 
 * @return  Void
 */
function yield($name, $substitutions=NULL) {
	global $locale;
	# determine the snippet's file name
	$name = str_replace(',', '', str_replace(' ', '_', $name));
	
	# if available, use the localized variant of the file
	if (file_exists("snippets/$locale/$name.html"))
		$data = file_get_contents("snippets/$locale/$name.html");
	# if no localization is present, the generic file should be used
	else if (file_exists("snippets/$name.html"))
		$data = file_get_contents("snippets/$name.html");
	# this is a last-ditch effort to avoid showing an error message to the user
	else if (file_exists("snippets/en/$name.html"))
		$data = file_get_contents("snippets/en/$name.html");
	# well, someone obviously botched this!
	else
		error('faulty configuration! missing snippet: '.$name);
	
	if (isset($substitutions)) {
		# determine the placeholder tokens to be expected in the snippet
		$placeholders = array_keys($substitutions);
		foreach($placeholders as $i => $label)
			$placeholders[$i] = sprintf('##%s##'
				, strtoupper($label)
			);
		
		# print the snippet with placeholder token replaced by real data
		print str_replace(
			$placeholders,
			$substitutions,
			$data
		);
	} else
		# print unmodified snippet
		print $data;
}
