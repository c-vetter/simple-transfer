<?php
/**
 * simple-transfer - a simple web app for asynchronously transferring single files
 * Copyright (c) 2010 rasenplanscher [ github.com/rasenplanscher ]
 */

# buffer output to prevent excess output in case of fatal errors
ob_start();

# setup the environment
require 'lib/database.php';
require 'lib/functions.php';
require 'lib/basedata.php';

# prevent unauthorized access
require 'lib/authentication.php';

# process the request
require strtolower($_SERVER['REQUEST_METHOD']).'.php';

