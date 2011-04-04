<?php
/**
 * rpTransfer - a simple web app for asynchronously transferring single files
 * Copyright (c) 2010 rasenplanscher [ code.rasenplanscher.info ]
 */

$pass =& $_configuration['authentication']['pass'];

if (
 !empty($_POST['login'])
 &&
 in_array($_POST['login'], $pass)
) {
	# client has sent login credentials and they check out
	$authorized = true;
	# allow list view if the sent credentials are those for list view
	$list_allowed = $_POST['login'] == $pass['list'];
	
	# have the client reload with a GET request
	header(sprintf('Location: %s'
		, $request_uri
	));
	exit;
}

# show the login page if the client is not authenticated
if (!$authorized)
	error(401, empty($_POST['login']) ?
		array('login, simple') :
		array('login failure', 'login, simple') ,
		array('uri' => $_SERVER['REQUEST_URI'])
	);
