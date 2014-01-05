<?php

return [
	/*
	|--------------------------------------------------------------------------
	| Qualtrics authentication parameters
	|--------------------------------------------------------------------------
	|
	| These are the default parameters that will be used to create a new 
	| object of type Qualtrics.  Set these here if your application will use
	| the same Qualtrics authentication parameters throughout.
	|
	| For cases in which you may be accessing mulitple different accounts or
	| libraries, it is recommended to use the IoC container to inject these
	| parameters at runtime to be associated with whatever object needs to 
	| access the Qualtrics API.
	|
	*/
	'username' => '',
	'token'    => '',
	'library'  => '',
];