<?php
/**
 * rpTransfer - a simple web app for asynchronously transferring single files
 * Copyright (c) 2010 rasenplanscher [ code.rasenplanscher.info ]
 */

/*
 * setup some variables that will be used in several places or within function while not in direct context
 */

# load the configuration
$_configuration = json_decode('{'.preg_replace('/#.*\n/', '', file_get_contents('CONFIGURATION')).'}', true);

# determine the uri the app was called with
$request_uri =& $_SERVER['REQUEST_URI'];

# determine the path to the application -- should ideally be '/'
$basepath = dirname($_SERVER['PHP_SELF']);

# determine how long a file stays valid
$file_ttl = time2seconds($_configuration['file ttl']);
# determine how long an expired file remains before it is deleted
$file_ttd = time2seconds($_configuration['file ttd']);

# identify the file to be downloaded if given as a hexadecimal number or the page to be shown, otherwise
$id = basename($request_uri);

# determine the valid length for file IDs
$id_length = $_configuration['ID length'];

# determine which locale to activate
$locale = $_configuration['locale'];

# setup conversion instructions for metadata types
$metadata_types = array(
	'expire' => 'integer',
	'size' => 'integer',
	'virgin' => 'boolean'
);

# determine the maximum filesize accepted for uploads
$max_filesize = sscanf(ini_get('upload_max_filesize'), '%u%s');
$max_filesize = $max_filesize[0] * ($max_filesize[1] ?
	str_replace(
		array('k', 'm', 'g'),
		array(1024, 1024*1024, 1024*1024*1024),
		strtolower($max_filesize[1])
	) :
	1 )
;

# establish a mapping of possible error codes to their configuration labels
$post_errors = array(
	UPLOAD_ERR_OK => 'success',
	UPLOAD_ERR_INI_SIZE => 'size error, ini',
	UPLOAD_ERR_FORM_SIZE => 'size error, form',
	UPLOAD_ERR_PARTIAL => 'partial upload',
	UPLOAD_ERR_NO_FILE => 'no file',
	UPLOAD_ERR_NO_TMP_DIR => 'no tmp directory',
	UPLOAD_ERR_CANT_WRITE => 'can\'t write',
	UPLOAD_ERR_EXTENSION => 'extension error',
	'FILE_TOO_LARGE' => 'web server abort',
	'FILE_NOT_SAVED' => 'not saved',
	'UNKNOWN_ERROR' => 'unknown error'
);

# determine how frequently to purge the uploaded files
$purge_interval = time2seconds($_configuration['purge interval']);
