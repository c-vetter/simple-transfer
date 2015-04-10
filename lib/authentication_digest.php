<?php
/**
 * simple-transfer - a simple web app for asynchronously transferring single files
 * Copyright (c) 2010 rasenplanscher [ github.com/rasenplanscher ]
 */

# gather some basic information
$nonce =& $_SESSION['nonce'];
$nonce_count =& $_SESSION['nonce_count'];
$realm =& $_configuration['authentication']['realm'];
$opaque = md5($realm);

# consider the client a fraud until identified, on a per-request basis
$authorized = false;
$list_allowed = false;

# if we don't know the nonce, the request doesn't really matter
# also, the client needs to provide its credentials to get any further
if (
 isset($nonce)
 and
 $_SERVER['PHP_AUTH_DIGEST']
) {
	# parse Authentication header as an array
	$authentication = array();
	preg_match_all('/(\w+)=(?:"([^"]+)"|([^\s,]+))/'
		, $_SERVER['PHP_AUTH_DIGEST']
		, $matches
		, PREG_SET_ORDER
	);
	foreach ($matches as $match)
		$authentication[$match[1]]
		 = $match[2]
		 ? $match[2]
		 : $match[3]
		;

	# logs out a client sending a different username after successful authentication
	if (!$username)
		$username = $authentication['username'];

	# if the nonce is different, either the client is a fraud or there has been an error
	# either way, further processing is futile
	if (
	 $authentication['nonce'] == $nonce
	 and
	 $authentication['username'] == $username
	) {
		# get data for given username
		$userdata = db_get_userdata($username);
		# determine the hash for the current request
		$request_hash = md5(sprintf('%s:%s'
			, $_SERVER['REQUEST_METHOD']
			, $request_uri
		));

		# determine the response hash the authentic client would send
		switch ($authentication['qop']) {
			case '':
				# legacy digest authentication
				$response_hash = md5(sprintf('%s:%s:%s'
					, $userdata['hash']
					, $nonce
					, $request_hash
				));
			break;

			case 'auth':
				# modern digest authentication, without integrity protection
				if ($authentication['nc'] > $nonce_count) {
					$nonce_count = $authentication['nc'];
					$response_hash = md5(sprintf('%s:%s:%s:%s:auth:%s'
						, $userdata['hash']
						, $nonce
						, $nonce_count
						, $authentication['cnonce']
						, $request_hash
					));
				}
			break;
		}

		# the client can be considered authentic if
		# the response hash could successfully be determined from the given credentials
		# and the sent response hash is identical to the calculated hash
		$authorized = $response_hash && ($authentication['response'] == $response_hash);
		# if the list flag is set, the client is allowed to access the list view
		$list_allowed = $authorized && $userdata['list'];
	}
}

# show the login page if the client is not authenticated
if (!$authorized) {
	# a new nonce has to be issued
	$nonce = sha1('nonce'.microtime());
	# nonce count and username must be reset
	# otherwise they would cause false negatives
	$nonce_count = null;
	$username = null;

	# the client must be asked for its credentials
	header(sprintf(
		'WWW-Authenticate: Digest realm="%s", domain="%s", nonce="%s", opaque="%s", algorithm="MD5", qop="auth"'
		, $realm
		, $basepath
		, $nonce
		, $opaque
	));

	# tell the user what's going on -- will be shown if she hits cancel
	error(401, empty($_SERVER['PHP_AUTH_DIGEST'])
		? NULL
		: array('login failure')
	);
}

