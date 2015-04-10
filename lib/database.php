<?php
/**
 * simple-transfer - a simple web app for asynchronously transferring single files
 * Copyright (c) 2010 rasenplanscher [ github.com/rasenplanscher ]
 */

/**
 * provide abstract functions to retrieve data from the database -- which is basically the file system
 */

/**
 * check whether there is data associated with a given id
 *
 * @param   String      the file's id
 * @param   Mixed       a string or an array of strings -- tells to look for file, log, metadata, or all of them, defaults to 'all'
 *
 * @return  Boolean     true if sought data was found, false otherwise
 */
function db_exists($id, $find='all') {
	# determine what to look for
	if (is_string($find)) {
		if ($find == 'all')
			$find = array('file', 'log', 'metadata');
		else
			$find = array($find);
	}

	# check for file
	if (
	 in_array('file', $find)
	 and
	 file_exists('files/'.$id)
	)
		return true;
	# check for log
	if (
	 in_array('log', $find)
	 and
	 file_exists('logs/'.$id)
	)
		return true;
	# check for metadata
	if (
	 in_array('metadata', $find)
	 and
	 file_exists('metadata/'.$id)
	)
		return true;

	# not found
	return false;
}

/**
 * get a set of metadata for a given id
 *
 * @param   String      the file's id
 *
 * @return  Hash        an associative array containing the metadata of the sought file or false if the id is not found
 */
function db_get_metadata($id) {
	global $metadata_types;

	$metadata = array();

	# read metadata from file
	$file = file('metadata/'.$id);
	# bail if the file could not be read
	if ($file === false) return false;

	# parse string data into array
	foreach ($file as $data) {
		$data = explode("\t", trim($data));
		$metadata[$data[0]] = $data[1];
		if (isset($metadata_types[$data[0]]))
			settype($metadata[$data[0]], $metadata_types[$data[0]]);
	}

	return $metadata;
}

/**
 * save a set of metadata for a given id
 *
 * @param   String      the file's id
 * @param   Hash        an associative array containing the metadata
 *
 * @return  Boolean     returns true if all steps succeeded, false otherwise
 */
function db_set_metadata($id, $data) {
	# open file
	$status = ($file = fopen('metadata/'.$id, 'w')) ? true : false;

	# write given data to file
	foreach ($data as $var => $val)
		$status = $status && fwrite($file, sprintf("%s\t%s\n"
			, $var
			, $val
		));

	# close file
	return $status && fclose($file);
}

/**
 * write a new message to the log associated with the given id
 *
 * @param   String      the file's id
 * @param   String      the log entry
 *
 * @return  Void
 */
function db_append_log($id, $entry) {
	# open file
	$file = fopen('logs/'.$id, 'a');

	# append new entry
	fwrite($file, sprintf("%s\t%s\n"
		, strftime('%Y-%m-%dT%H:%M:%S')
		, $entry
	));

	# close file
	fclose($file);
}

/**
 * retrieve a list of all available files
 * sorted by expiration date, newest first
 *
 * @return  Array       an array of metadata sets, indexed by id
 */
function db_list() {
	$files = Array();

	# open directory
	$metadata = dir('metadata');

	# get metadata from files
	while($file = $metadata->read()) {
		if (substr($file, 0, 1) == '.')
			continue;
		$files[] = db_get_metadata($file);
		$files[count($files)-1]['id'] = $file;
	}

	# sort by expiration date
	$sort = array();
	foreach ($files as $i => $file)
	    $sort[$i]  = $file['expire'];
	array_multisort($sort, SORT_DESC, $files);

	# return sorted list of files
	return $files;
}

/**
 * remove all metadata that have been expired for more than file ttd
 * then remove all files and logs without metadata
 *
 * @return  Void
 */
function db_purge() {
	global $purge_interval, $file_ttd;

	# abort if last purge has been less than purge interval ago
	if (filemtime('metadata/.LAST_PURGE') > time() - $purge_interval)
		return;

	# get list of files, oldest first
	$files = array_reverse(db_list());

	# remove metadata of expired files
	foreach($files as $file) {
		if ($file['expire']+$file_ttd > time())
			# continue to next step when first file is encountered that has not expired more than file ttd ago
			break;
		else
			unlink('metadata/'.$file['id']);
	}

	# open log directory
	$files = dir('logs');

	# remove all log files without associated metadata
	while($file = $files->read())
		if (substr($file, 0, 1) == '.')
			continue;
		else if (!db_exists($file, 'metadata'))
			unlink('logs/'.$file);

	# open file directory
	$files = dir('files');

	# remove all uploaded files without associated metadata
	while($file = $files->read())
		if (substr($file, 0, 1) == '.')
			continue;
		else if (!db_exists($file, 'metadata'))
			unlink('files/'.$file);

	# timestamp this purge
	touch('metadata/.LAST_PURGE');
}



/**
 * retrieve the data associated with the given name
 *
 * @param   String      the username
 *
 * @return  Hash        an associative array containing the data of the given user or false if the user name is not found
 */
function db_get_userdata($name) {
	$user = array();

	# read user file
	$file = file('users/'.$name);
	# if there is no file, bail
	if ($file === false) return false;

	# parse string data into array
	foreach ($file as $data) {
		$data = explode("\t", trim($data));
		$user[$data[0]] = $data[1];
	}
	# convert list flag to boolean
	settype($user['list'], 'boolean');

	# return array with user data
	return $user;
}

