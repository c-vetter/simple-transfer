<?php
/**
 * rpTransfer - a simple web app for asynchronously transferring single files
 * Copyright (c) 2010 rasenplanscher [ code.rasenplanscher.info ]
 * 
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the MIT License as published at
 * http://www.opensource.org/licenses/mit-license.php
 * 
 * This basically means: do whatever you like with it, but include
 * the license and don't sue for any reason. Find the complete
 * license in the accompanying file "LICENSE".
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
