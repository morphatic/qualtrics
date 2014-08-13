<?php
/**
 * Qualtrics.php
 *
 * This file contains the implementation of the core functionality
 * of this package.
 */

namespace Morphatic\Qualtrics;
use Illuminate\Support\Facades\Config;

/**
 * Qualtrics class
 *
 * This class implements wrapper functions for all of the API 
 * methods available in the Qualtrics 2.2 API.  It is meant to
 * be used from within Laravel 4.
 * 
 * @author Morgan Benton <morgan@morphatic.com>
 */
class Qualtrics {
	
	/**
	 * Qualtrics username
	 *
	 * This is the email address that serves as the Qualtrics 
	 * username that has access to the api
	 *
	 * @var string $username
	 */
	private $username;

	/**
	 * Qualtrics token
	 *
	 * This is the Qualtrics-generated that gives a user 
	 * access to the api
	 *
	 * @var string $token
	 */
	private $token;

	/**
	 * Qualtrics library
	 *
	 * This is a library ID associated with the 
	 * username that has access to the api
	 *
	 * @var string $library
	 */
	private $library;
	
	/**
	 * Qualtrics API version
	 *
	 * This is a Qualtrics API version used by
	 * this package
	 *
	 * @var string $version
	 */
	private $version = '2.2';
	
	/**
	 * Creates a new object of type Qualtrics, using parameters stored in the config file
	 */
	public function __construct() {
		
		// check for the necessary parameters
		if ( empty( Config::get( 'qualtrics::username' ) ) || empty( Config::get( 'qualtrics::token' ) ) )
			throw new MissingParameterException(
				"The Qualtrics username and/or API token was unspecified in the config file"
			);
			
		// set the necessary credentials from the config files
		$this->username = Config::get( 'qualtrics::username' );
		$this->token    = Config::get( 'qualtrics::token'    );
		$this->library  = Config::get( 'qualtrcis::library'  );
		
		// make a request to make sure the credentials are correct
		$this->getUserInfo();
	}
	
	/**
	 * Creates a new object of type Qualtrics, using parameters passed in by the user
	 */
	public function __construct( $username, $token, $library = null ) {
		
		if ( empty( $username ) || empty( $token ) )
			throw new MissingParameterException(
				"The Qualtrics username and/or API token was unspecified"
			);
		// get the necessary credentials from the config files
		$this->username = $username;
		$this->token    = $token;
		$this->library  = $library;
		
		// make a request to make sure the credentials are correct
		$this->getUserInfo();
	}
	
	/**
	 * Checks to see whether this instance has a library
	 *
	 * @return boolean True or false
	 */
	public function hasLibrary() {
		return ! empty( $this->library );
	}
	
	/**
	 * Converts XML to Array that can then be converted to JSON
	 *
	 * @param \SimpleXMLElement $xml The XML to be converted
	 * @param object[]          $out An empty array to be populated by the function
	 * @return array The XML converted to an array
	 */
	private function xmlToArray( $xml, $out = array() ) {
		foreach ( (array) $xml as $index => $node )
			$out[ $index ] = ( is_object( $node ) || is_array( $node ) ) ? $this->xmlToArray( $node ) : $node;
		return $out;
	}
	
	/**
	 * Converts csv into 2-dimensional array
	 *
	 * @param string  $csv_string 		The CSV string to be parsed
	 * @param string  $delimiter		The delimiter to be used for parsing, defaults to comma
	 * @param boolean $skip_empty_lines	Whether or not to skip empty lines, defaults to true
	 * @param boolean $trim_fields		Whether or not to trim() fields, defaults to true
	 * @return array The two-dimensional array representing the CSV
	 */
	private function parse_csv ( $csv_string, $delimiter = ",", $skip_empty_lines = true, $trim_fields = true) {
	    $enc = preg_replace('/(?<!")""/', '!!Q!!', $csv_string);
	    $enc = preg_replace_callback(
	        '/"(.*?)"/s',
	        function ($field) {
	            return urlencode(utf8_encode($field[1]));
	        },
	        $enc
	    );
	    $lines = preg_split($skip_empty_lines ? ($trim_fields ? '/( *\R)+/s' : '/\R+/s') : '/\R/s', $enc);
	    return array_map(
	        function ($line) use ($delimiter, $trim_fields) {
	            $fields = $trim_fields ? array_map('trim', explode($delimiter, $line)) : explode($delimiter, $line);
	            return array_map(
	                function ($field) {
	                    return str_replace('!!Q!!', '"', utf8_decode(urldecode($field)));
	                },
	                $fields
	            );
	        },
	        $lines
	    );
	}

	/**
	 * Parses the data returned from a Qualtrics request.
	 *
	 * @param resource $ch 		The curl handle associated with the request
	 * @param string   $result  The text returned from the request
	 * @throws Exceptions\QualtricsXMLException  Thrown when an XML response cannot be parsed correctly
	 * @throws Exceptions\UnknownFormatException Thrown when Qualtrics returns a response with an unrecognized content-type header
	 * @return array Returns an array with the headers and parsed response text
	 */
	private function parseResponse( $ch, $result ) {
		
		// determine where the headers end and the response begins
		$header_length = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		
		// get the raw headers
		$raw_headers = substr( $result, 0, $header_length );
		
		// get the raw response
		$response = substr( $result, $header_length );
		
		// parse the headers
		$headers = array();
		foreach ( explode( "\r\n", $raw_headers ) as $i => $line ) {
			if ( preg_match( '/^http.*?(\d{3}).*?/i', $line, $http_code ) ) {
				$headers[ 'http_code' ] = $http_code[ 1 ];
			} elseif ( '' !== trim( $line ) ) {
				list( $key, $value ) = explode( ': ', $line );
				$headers[ strtolower( $key ) ] = $value;
			}
		}
		
		// determine the content-type and parse the response
		switch ( $headers[ 'content-type' ] ) {
			// XML
			case 'text/xml':
			case 'text/xml; charset=utf-8':
				libxml_use_internal_errors( true );
				$response = new \SimpleXMLElement( $response, LIBXML_NOCDATA );
				if ( ! $response ) {
					$errors = libxml_get_errors();
					throw new Exceptions\QualtricsXMLException( 'Qualtrics XML Parse Error: ' . $errors[ 0 ]->message, $errors[ 0 ]->code );
				}
				$response = json_decode( json_encode( $this->xmlToArray( $response ) ) );
				break;
			// JSON
			case 'application/json':
				$response = json_decode( $response );
				break;
			// CSV (MS Excel format)
			case 'application/vnd.msexcel':
				$response = $this->parse_csv( $response );
				break;
			// HTML
			case 'text/html; charset=UTF-8':
				// TODO
				break;
			// Something other than XML, JSON, HTML, or CSV
			default:
				throw new Exceptions\UnknownFormatException( 'Qualtrics returned a response in an unknown format: ' . $headers[ 'content-type' ] );
				break;
		}
		
		return array( 'headers' => $headers, 'response' => $response );
	}
	
	
	/**
	 * Private function that makes a generic Qualtrics API request.
	 *
	 * This function takes an API function name and an array of parameters
	 * and makes an attempt to fetch the data using the Qualtrics API.
	 *
	 * @param string         $request The name of the API function to be called
	 * @param string[] $params  An array which contains any necessary parameters for the API call
	 * @throws Exceptions\CurlNotInstalledException Thrown when the curl library is not installed on the server
	 * @throws Exceptions\QualtricsException        Thrown when Qualtrics returns an error with the request
	 * @throws Exceptions\CurlException             Thrown when the curl request is unsuccessful, e.g. request timeout
	 * @return object Returns an object representing the requested data
	 */
	private function request( $request, $params = array() ) {
		
		// throw an exception if curl is not installed
		if ( ! function_exists( 'curl_init' ) ) throw new Exceptions\CurlNotInstalledException( 'Curl does not appear to be installed on your server.' );

		// create a new curl handle
		$ch = curl_init( 'https://survey.qualtrics.com/WRAPI/ControlPanel/api.php' );
		
		// set the parameters for the request
		$params = array_merge( array(
				'Request' => $request,
				'User'	  => $this->username,
				'Token'   => $this->token,
				'Version' => $this->version,
			), $params
		);
		
		// set curl options
		curl_setopt_array( $ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $params,
			CURLOPT_CONNECTTIMEOUT => 0
		));
		
		// make the request
		$result = curl_exec( $ch );

		// handle the result
		if ( false !== $result ) {
			// request was successful, parse the result
			$result = $this->parseResponse( $ch, $result, $request );
			if ( isset( $result[ 'response' ]->Meta->Status ) ) {
				if ( 'Success' == $result[ 'response' ]->Meta->Status ) {
					return $result[ 'response' ]->Result;
				} else {
					// Qualtrics returned an error
					throw new Exceptions\QualtricsException( 'Qualtrics error: ' . $result[ 'response' ]->Meta->ErrorMessage, $result[ 'response' ]->Meta->ErrorCode );
				}
			} elseif ( '200' == $result[ 'headers' ][ 'http_code' ] ) {
				// some responses, e.g. getLegacyResponseData do NOT have a Meta section
				return $result[ 'response' ];
			} else {
				// some other error
				// TODO
			}
		} else {
			// there was an error with the curl request
			throw new Exceptions\CurlException( 'Curl error: ' . curl_error( $ch ), curl_errno( $ch ) );
		}
		curl_close( $ch );
	}
	
	/**
	 * Gets the total number of responses for a survey in a given date range
	 *
	 * ```
	 * $params = array(
	 *		'Format'    => 'JSON',               // Must be XML or JSON, defaults to JSON
	 *		'StartDate' => 'YYYY-MM-DD',         // Start date of responses to include
	 *		'EndDate'   => 'YYYY-MM-DD',         // (optional) defaults to today's date
	 *		'SurveyID'  => 'SV_9LC2rrUZT8c2EiF', // the Qualtrics ID of the survey in question.
	 * );
	 * ```
	 *
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object Returns a PHP object with the resulting counts of "Auditable", "Generated" and "Deleted" responses
	 */
	function getResponseCountsBySurvey( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'    => 'JSON',
			'StartDate' => null,
			'EndDate'   => date( 'Y-m-d' ),
			'SurveyID'  => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'StartDate' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required StartDate parameter was not specified' );
		if ( ! $params[ 'SurveyID'  ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required SurveyID parameter was not specified' );
	
		// try to fetch and return the result
		return $this->request( 'getResponseCountsBySurvey', $params );
	}

	/**
	 * Gets information about a user
	 *
	 * ```
	 * $params = array(
	 *		'Format' => 'JSON', // Must be XML or JSON, defaults to JSON
	 * );
	 * ```
	 *
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object Information about the user in question
	 */
	function getUserInfo( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array( 'Format' => 'JSON' ), $params );
	
		// try to fetch  and return the results
		return $this->request( 'getUserInfo', $params );
	}

	/**
	 * Add a new recipient to a panel
	 *
	 * ```
	 * $params = array(
	 *		'Format'          => 'JSON',               // Must be XML or JSON, defaults to JSON
	 *		'LibraryID'       => 'GR_6G4LaoiroGxHH12', // Start date of responses to include
	 *		'PanelID'         => 'ML_5yIfnFP0soZK3GJ', // Start date of responses to include
	 *		'FirstName'       => 'Douglas',            // Recipient's first name
	 *		'LastName'        => 'Crockford',          // Recipient's last name
	 *		'Email'           => 'doug@js.org',        // Recipient's email address
	 *		'ExternalDataRef' => 'crockdx',            // (optional) 
	 *		'Language'        => 'EN',                 // (optional) language, defaults to EN
	 *		'ED[***]'         => '@dougcrockford',     // (optional) embedded data, e.g. ED[twitterID]
	 *												   // multiple values for ED possible
	 * );
	 * ```
	 *
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object Returns true on success
	 */
	function addRecipient( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'    => 'JSON',
			'LibraryID' => $this->library,
			'PanelID'   => null,
			'FirstName' => null,
			'LastName'  => null,
			'Email'     => null,
			'Language'  => 'EN',
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'PanelID'   ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required PanelID parameter was not specified' );
		if ( ! $params[ 'FirstName' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required FirstName parameter was not specified' );
		if ( ! $params[ 'LastName'  ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required LastName parameter was not specified' );
		if ( ! $params[ 'Email'     ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required Email parameter was not specified' );
	
		// fetch the response
		return $this->request( 'addRecipient', $params );
	}

	/**
	 * Creates a distribution for survey and a panel.  No emails will be sent.  Distribution links can be generated later to take the survey.
	 *
	 * @param string Format Must be XML or JSON, JSON is default
	 * @param string SurveyID The ID of the survey to create a distribution for
	 * @param string PanelID The ID of the panel for the distribution
	 * @param string Description A description of the distribution
	 * @param string PanelLibraryID The library ID of the panel
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object An object containing the DistributionID
	 */
	function createDistribution( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'         => 'JSON',
			'PanelLibraryID' => $this->library,
			'PanelID'        => null,
			'SurveyID'       => null,
			'Description'    => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'PanelID'     ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required PanelID parameter was not specified' );
		if ( ! $params[ 'SurveyID'    ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required SurveyID parameter was not specified' );
		if ( ! $params[ 'Description' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required Description parameter was not specified' );
	
		// fetch and return the response
		return $this->request( 'createDistribution', $params );
	}

	/**
	 * Creates a new Panel in the Qualtrics System and returns the ID of the new panel
	 *
	 * @param string Format Must be JSON or XML, defaults to JSON
	 * @param string LibraryID The library ID of the user creating the panel
	 * @param string Name A name for the new panel
	 * @param string Category (optional) The category the panel is created in
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object Returns the ID of the new panel
	 */
	function createPanel( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'    => 'JSON',
			'LibraryID' => $this->library,
			'Name'      => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'Name' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required Name parameter was not specified' );
	
		// fetch and return the response
		return $this->request( 'createPanel', $params );
	}

	/**
	 * Gets all the panel members for the given panel
	 * 
	 * @param string Format Must be XML, CSV, or HTML, defaults to CSV
	 * @param string LibraryID The library ID of the panel
	 * @param string PanelID The panel ID you want to export
	 * @param string EmbeddedData A comma-separated list of the embedded data keys you want to export. Only required for CSV export. XML includes all embedded data.
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object The panel members and any requested data
	 */
	function getPanel( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'    => 'CSV',
			'LibraryID' => $this->library,
			'PanelID'   => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'PanelID' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required PanelID parameter was not specified' );
	
		// fetch and return the response
		return $this->request( 'getPanel', $params );
	}

	/**
	 * Deletes a panel
	 * 
	 * @param string Format Must be XML, CSV, or HTML, defaults to CSV
	 * @param string LibraryID The library ID of the panel
	 * @param string PanelID The panel ID you want to export
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object The results of the deletion process
	 */
	function deletePanel( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'    => 'JSON',
			'LibraryID' => $this->library,
			'PanelID'   => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'PanelID' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required PanelID parameter was not specified' );
	
		// fetch and return the response
		return $this->request( 'deletePanel', $params );
	}

	/**
	 * Gets the number of panel members
	 * 
	 * @param string Format Must be XML or JSON, defaults to JSON
	 * @param string LibraryID The library ID of the panel
	 * @param string PanelID The panel ID you want to get a count for
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object The number of members of the panel
	 */
	function getPanelMemberCount( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'    => 'JSON',
			'LibraryID' => $this->library,
			'PanelID'   => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'PanelID' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required PanelID parameter was not specified' );
	
		// fetch and return the response
		return $this->request( 'getPanelMemberCount', $params );
	}

	/**
	 * Gets all of the panels in a given library
	 * 
	 * @param string Format Must be XML or JSON, defaults to JSON
	 * @param string LibraryID The library ID of the panel
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object The panels in the library
	 */
	function getPanels( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'    => 'JSON',
			'LibraryID' => $this->library,
			), $params
		);
	
		// fetch and return the response
		return $this->request( 'getPanels', $params );
	}

	/**
	 * Gets a representation of the recipient and their history
	 * 
	 * @param string Format Must be XML or JSON, defaults to JSON
	 * @param string LibraryID The library ID of the panel
	 * @param string RecipientID The recipient ID you want to get
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object The representation of the recipient
	 */
	function getRecipient( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'      => 'JSON',
			'LibraryID'   => $this->library,
			'RecipientID' => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'RecipientID' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required RecipientID parameter was not specified' );
	
		// fetch and return the response
		return $this->request( 'getRecipient', $params );
	}

	/**
	 * Imports a csv file as a new panel
	 *
	 * Imports a csv file as a new panel (optionally it can append to a previously made panel) 
	 * into the database and returns the panel id.  The csv file can be posted (there is an 
	 * approximate 8 megabytes limit) or a url can be given to retrieve the file from a remote server.  
	 * The csv file must be comma separated using " for encapsulation.
	 * 
	 * @param string Format         Must be XML or JSON, JSON is default
	 * @param string LibraryID      The library ID into which you want to import the panel
	 * @param string ColumnHeaders  0:1 If headers exist, these can be used when importing embedded data, defaults to 1
	 * @param string Email          The number of the column that contains the email address
	 * @param string URL            (optional) If given, then the CSV file will be downloaded into Qualtrics from this URL
	 * @param string Name           (optional) The name of the panel if creating a new one
	 * @param string PanelID        (optional) If given, indicates the ID of the panel to be updated
	 * @param string FirstName      (optional) The number of the column containing recipients' first names
	 * @param string LastName       (optional) The number of the column containing recipients' last names
	 * @param string ExternalRef    (optional) The number of the column containing recipients' external data reference
	 * @param string Language       (optional) The number of the column containing recipients' languages
	 * @param string AllED          (optional) 0:1 If set to 1, will import all non-used columns as embedded data, and you won't have to set the EmbeddedData parameter
	 * @param string EmbeddedData   (optional) Comma-separated list of column numbers to treat as embedded data
	 * @param string Category       (optional) Sets the category for the panel
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object Contains the new PanelID, a count of imported recipients, and a count of ignored recipients
	 */
	function importPanel( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'        => 'JSON',
			'LibraryID'     => $this->library,
			'ColumnHeaders' => 1,
			'Email'         => null,
			), $params
		);
	
		// TODO: Figure out how to handle POST file uploads with this method
	
		// check for missing values
		if ( ! $params[ 'Email' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required Email parameter was not specified' );
	
		// fetch and return the response
		return $this->request( 'importPanel', $params );
	}

	/**
	 * Removes a specified panel member from a specified panel
	 * 
	 * @param string Format Must be XML or JSON, defaults to JSON
	 * @param string LibraryID The library ID of the panel
	 * @param string PanelID The ID of the panel from which the recipient will be removed
	 * @param string RecipientID The recipient ID you want to remove
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object The representation of the recipient
	 */
	function removeRecipient( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'      => 'JSON',
			'LibraryID'   => $this->library,
			'PanelID'     => null,
			'RecipientID' => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'PanelID'     ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required PanelID parameter was not specified' );
		if ( ! $params[ 'RecipientID' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required RecipientID parameter was not specified' );
	
		// fetch and return the response
		return $this->request( 'removeRecipient', $params );
	}

	/**
	 * Sends a reminder through the Qualtrics mailer to the panel or individual as specified by the parent distribution Id
	 *
	 * @param string Format                     Must be XML or JSON, JSON is default
	 * @param string ParentEmailDistributionID  The parent distribution you are reminding
	 * @param string SendDate                   YYYY-MM-DD hh:mm:ss when you wan the mailing to go out, defaults to now
	 * @param string FromEmail                  The email address from which the email should appear to originate
	 * @param string FromName                   The name the message will appear to be from
	 * @param string Subject                    The subject for the email
	 * @param string MessageID                  The ID of the message from the message library to be sent
	 * @param string LibraryID                  The ID of the library that contains the message to be sent
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object The email distribution ID and distribution queue id
	 */
	function sendReminder( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'                    => 'JSON',
			'LibraryID'                 => $this->library,
			'ParentEmailDistributionID' => null,
			'SendDate'                  => date( 'Y-m-d H:i:s', time() - ( 60 * 119 ) ), //TODO: investigate if this time shuffling is still necessary...
			'FromEmail'                 => null,
			'FromName'                  => null,
			'Subject'                   => null,
			'MessageID'                 => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'ParentEmailDistributionID' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required ParentEmailDistributionID parameter was not specified' );
		if ( ! $params[ 'FromEmail' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required FromEmail parameter was not specified' );
		if ( ! $params[ 'FromName'  ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required FromName parameter was not specified' );
		if ( ! $params[ 'Subject'   ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required Subject parameter was not specified' );
		if ( ! $params[ 'MessageID' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required MessageID parameter was not specified' );
	
		// fetch and return the response
		return $this->request( 'sendReminder', $params );
	}

	/**
	 * Sends a survey through the Qualtrics mailer to the individual specified
	 *
	 * @param string Format            Must be XML or JSON, JSON is default
	 * @param string SurveyID          The ID of the survey to be sent
	 * @param string SendDate          YYYY-MM-DD hh:mm:ss when you wan the mailing to go out, defaults to now
	 * @param string FromEmail         The email address from which the email should appear to originate
	 * @param string FromName          The name the message will appear to be from
	 * @param string Subject           The subject for the email
	 * @param string MessageID         The ID of the message from the message library to be sent
	 * @param string MessageLibraryID  The ID of the library that contains the message to be sent
	 * @param string PanelID           The ID of the message from the message library to be sent
	 * @param string PanelLibraryID    The ID of the library that contains the message to be sent
	 * @param string RecipientID       The recipient ID of the person to whom to send the survey
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object The email distribution ID and distribution queue id
	 */
	function sendSurveyToIndividual( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'           => 'JSON',
			'MessageLibraryID' => $this->library,
			'PanelLibraryID'   => $this->library,
			'SurveyID'         => null,
			'SendDate'         => date( 'Y-m-d H:i:s', time() - ( 60 * 119 ) ), //TODO: investigate if this time shuffling is still necessary...
			'FromEmail'        => null,
			'FromName'         => null,
			'Subject'          => null,
			'MessageID'        => null,
			'PanelID'          => null,
			'RecipientID'      => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'SurveyID'    ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required SurveyID parameter was not specified' );
		if ( ! $params[ 'FromEmail'   ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required FromEmail parameter was not specified' );
		if ( ! $params[ 'FromName'    ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required FromName parameter was not specified' );
		if ( ! $params[ 'Subject'     ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required Subject parameter was not specified' );
		if ( ! $params[ 'MessageID'   ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required MessageID parameter was not specified' );
		if ( ! $params[ 'PanelID'     ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required PanelID parameter was not specified' );
		if ( ! $params[ 'RecipientID' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required RecipientID parameter was not specified' );
	
		// fetch and return the response
		return $this->request( 'sendSurveyToIndividual', $params );
	}

	/**
	 * Sends a survey through the Qualtrics mailer to the panel specified
	 *
	 * @param string Format            Must be XML or JSON, JSON is default
	 * @param string SurveyID          The ID of the survey to be sent
	 * @param string SendDate          YYYY-MM-DD hh:mm:ss when you wan the mailing to go out, defaults to now
	 * @param string FromEmail         The email address from which the email should appear to originate
	 * @param string FromName          The name the message will appear to be from
	 * @param string Subject           The subject for the email
	 * @param string MessageID         The ID of the message from the message library to be sent
	 * @param string MessageLibraryID  The ID of the library that contains the message to be sent
	 * @param string PanelID           The ID of the message from the message library to be sent
	 * @param string PanelLibraryID    The ID of the library that contains the message to be sent
	 * @param string ExpirationDate    (optional) YYYY-MM-DD hh:mm:ss when the survey invitation expires
	 * @param string LinkType          (optional) {Individual,Multiple,Anonymous} Defaults to Individual
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object The email distribution ID and distribution queue id
	 */
	function sendSurveyToPanel( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'           => 'JSON',
			'MessageLibraryID' => $this->library,
			'PanelLibraryID'   => $this->library,
			'SurveyID'         => null,
			'SendDate'         => date( 'Y-m-d H:i:s', time() - ( 60 * 119 ) ), //TODO: investigate if this time shuffling is still necessary...
			'FromEmail'        => null,
			'FromName'         => null,
			'Subject'          => null,
			'MessageID'        => null,
			'PanelID'          => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'SurveyID'  ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required SurveyID parameter was not specified' );
		if ( ! $params[ 'FromEmail' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required FromEmail parameter was not specified' );
		if ( ! $params[ 'FromName'  ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required FromName parameter was not specified' );
		if ( ! $params[ 'Subject'   ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required Subject parameter was not specified' );
		if ( ! $params[ 'MessageID' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required MessageID parameter was not specified' );
		if ( ! $params[ 'PanelID'   ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required PanelID parameter was not specified' );
	
		// fetch and return the response
		return $this->request( 'sendSurveyToPanel', $params );
	}

	/**
	 * Updates the recipient’s data—any value not specified will be left alone and not updated
	 *
	 * @param string Format           Must be XML or JSON, defaults to JSON
	 * @param string LibraryID        The LibraryID of the user who owns the panel
	 * @param string RecipientID      The ID of the recipient whose data will be updated
	 * @param string FirstName        First name of the new recipient
	 * @param string LastName         Last name of the new recipient
	 * @param string Email            Email address of the new recipient
	 * @param string ExternalDataRef  (optional) A value to store in the external data ref for the user (should default to the WordPress username)
	 * @param string Language         (optional) The language code for the user, e.g. EN, defaults to EN
	 * @param string ED[***]          (optional) An embedded data value, there can be many, and takes the form e.g. ED[skypeID]=mcbenton
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object Returns true on success or WP_Error on failure
	 **/
	function updateRecipient( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'      => 'JSON',
			'LibraryID'   => $this->library,
			'RecipientID' => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'RecipientID' ] ) throw new Exceptions\MissingParameterExtension( 'Missing Parameter: The required RecipientID parameter was not specified' );
		if ( ! $params[ 'RecipientID' ] ) throw new Exceptions\MissingParameterException( 'Missing Parameter: The required RecipientID parameter was not specified' );
	
		// fetch the response
		$response = $this->request( 'updateRecipient', $params );
		return ( ! is_wp_error( $response ) ) ? true : $response;
	}

	/**
	 * Returns all of the response data for a survey in the original (legacy) data format
	 *
	 * @param string Format             {XML,JSON,CSV,HTML} default is JSON
	 * @param string SurveyID           The ID of the survey you'll be getting responses for
	 * @param string LastResponseID     (optional) When specified it will export all responses after the ID specified
	 * @param string Limit              (optional) Max number of responses to return
	 * @param string ResponseID         (optional) ID of an individual response to be returned
	 * @param string ResponseSetID      (optional) ID of a response set to return, if unspecified returns default response set
	 * @param string SubgroupID         (optional) Subgroup you want to download data for
	 * @param string StartDate          (optional) YYYY-MM-DD hh:mm:ss Date the responses must be after
	 * @param string EndDate            (optional) YYYY-MM-DD hh:mm:ss Date the responses must be before
	 * @param string Questions          (optional) Comma-separated list of question ids
	 * @param string Labels             (optional) If 1 (true) the label for choices and answers will be used, not the ID. Default is 0
	 * @param string ExportTags         (optional) If 1 (true) the export tags will be used rather than the V labels. Default is 1
	 * @param string ExportQuestionIDs  (optional) If 1 (true) the internal question IDs will be used rather than export tags or V labels. Default is 0
	 * @param string LocalTime          (optional) If 1 (true) the StartDate and EndDate will be exported using the specified user's local time zone. Default is 1
	 * @param string UnansweredRecode   (optional) The recode value for seen but unanswered questions. If not specified a blank value is put in for these questions.
	 * @param string PanelID            (optional) If supplied it will only get the results for the members of the panel specified
	 * @param string ResponseInProgress (optional) If 1 (true) will retrieve the responses in progress (and only the responses in progress )
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object An object representing the responses for the specified panel members
	 */
	public function getLegacyResponseData( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format'     => 'JSON',
			'SurveyID'   => null,
			'ExportTags' => 1,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'SurveyID' ] ) throw new Exceptions\MissingParameterExtension( 'Missing Parameter: The required SurveyID parameter was not specified' );
	
		// fetch the response
		$response = $this->request( 'getLegacyResponseData', $params );
		// standardize the response format to an array of objects with the same properties
		switch ( $params[ 'Format' ] ) {
			case 'XML': $response = $response->Response; break;
			case 'JSON':
				$responses = array();
				foreach ( $response as $rid => $r ) {
					$r->ResponseID = $rid;
					$responses[] = $r;
				} 
				$response = $responses;
				break;
			case 'CSV':
				$keys = $values = array();
				foreach ( $response as $i => $r ) {
					foreach ( $r as $j => $v ) {
						if ( $i != 0 ) {
							if ( $i == 1 ) {
								$keys[ $j ] = preg_match( '/Q\d+/i', $response[ 0 ][ $j ] ) ? $response[ 0 ][ $j ] : $v;
							} else {
								if ( ! isset( $values[ $i ] ) ) $values[ $i ] = new \stdClass;
								if ( '' != $keys[ $j ] ) $values[ $i ]->{$keys[ $j ]} = $v;
							}
						}
					}
				}
				$response = array_values( $values );
				break;
		}
		return $response;
	}

	/**
	 * This request returns an xml export of the survey. NOTE: Custom response format!
	 *
	 * @param string SurveyID     The ID of the survey to be exported
	 * @param string ExportLogic  If 1 (true) it will export the logic. EXPERIMENTAL
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object  On object representing the survey.
	 */
	function getSurvey( $params = array() )	{
		// set the parameters for this request
		$params = array_merge( array(
			'SurveyID' => null,
			), $params
		);
	
		// check for missing values
		if ( ! $params[ 'SurveyID' ] ) throw new Exceptions\MissingParameterExtension( 'Missing Parameter: The required SurveyID parameter was not specified' );
	
		// fetch the response
		// TODO: This function returns data in a non-standard format.  Needs to be handled specially.
		return $this->request( 'getSurvey', $params );
	}

	/**
	 * This request returns a list of all the surveys for the user
	 *
	 * @param string Format  {XML,JSON} Default is JSON
	 * @param string[] $params An array of named parameters needed to complete the request (see code above)
	 * @throws Exceptions\MissingParameterException Thrown when one of the required parameters does not exist in the $params array
	 * @return object  A list of surveys available to the user
	 */
	function getSurveys( $params = array() ) {
		// set the parameters for this request
		$params = array_merge( array(
			'Format' => 'JSON',
			), $params
		);
	
		// fetch and return the response
		$response = $this->request( 'getSurveys', $params );
		return 'XML' === $params[ 'Format' ] ? $response->Surveys->element : $response->Surveys;
	}

}