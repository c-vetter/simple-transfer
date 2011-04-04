<?php
/**
 * rpTransfer - a simple web app for asynchronously transferring single files
 * Copyright (c) 2010 rasenplanscher [ code.rasenplanscher.info ]
 */

if ($_SERVER['REQUEST_METHOD'] == 'POST'
 || $request_uri == $basepath
 || $request_uri == $basepath.'list'
) {
	# if $basepath differs from request uri and the request method is GET,
	# consider this a download request and don't require the user to login,
	# unless the request uri is $basepath appended with the list request
	
	# start session management
	session_start();
	# link permission flags to session data
	$authorized =& $_SESSION['authorized'];
	$list_allowed =& $_SESSION['list_allowed'];
	$username =& $_SESSION['username'];
	
	# call authentication procedure
	if (!include sprintf('authentication_%s.php'
		, $_configuration['authentication']['mode']
	))
		error('faulty configuration!');
}
