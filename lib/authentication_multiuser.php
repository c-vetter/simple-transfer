<?php
/**
 * simple-transfer - a simple web app for asynchronously transferring single files
 * Copyright (c) 2010 rasenplanscher [ github.com/rasenplanscher ]
 */

if (!empty($_POST['username'])) {
	# get data for given user name
	$userdata = db_get_userdata($_POST['username']);
	# if the pass checks out, the client is authenticated
	$authorized = $_POST['pass'] ?
		$userdata['pass'] == $_POST['pass']:
		false;
	# if the list flag is set, the client is allowed to access the list view
	$list_allowed = $authorized && $userdata['list'];

	if ($authorized) {
		# remember the username
		$username = $_POST['username'];

		# have the client reload with a GET request
		header(sprintf('Location: %s'
			, $request_uri
		));
		exit;
	}
}

# show the login page if the client is not authenticated
if (!$authorized)
	error(401,
		empty($_POST['username']) && empty($_POST['pass']) ?
		array('login, multiuser') :
		array('login failure', 'login, multiuser') ,
		array('uri' => $_SERVER['REQUEST_URI'])
	);

