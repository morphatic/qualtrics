<?php
/**
 * Qualtrics.php
 *
 * This file contains the implementation of the core functionality
 * of this package.
 */

namespace Morphatic\Qualtrics;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client;

/**
 * Qualtrics class
 *
 * This class implements wrapper functions for all of the API 
 * methods available in the Qualtrics 2.3 API.  It is meant to
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
	private $version = '2.3';
	
	/**
	 * Qualtrics API URL
	 *
	 * This is a Qualtrics API URL used by
	 * this package
	 *
	 * @var string $api_url
	 */
	private $api_url = 'https://survey.qualtrics.com/WRAPI/ControlPanel/api.php';
	
	/**
	 * Guzzle client (http://guzzle.readthedocs.org/)
	 *
	 * Used to make all of our API requests
	 *
	 * @var string $api_url
	 */
	private $client;
	
	/**
	 * Creates a new object of type Qualtrics
	 */
	public function __construct( $username = null, $token = null, $library = null, $client = null ) {
		
		// check for the necessary parameters
		$username = is_null( $username ) ? Config::get( 'qualtrics::username' ) : $username;
		$token    = is_null( $token    ) ? Config::get( 'qualtrics::token'    ) : $token;
		$library  = is_null( $library  ) ? Config::get( 'qualtrics::library'  ) : $library;
		
		if ( empty( $username ) || empty( $token ) )
			throw new Exceptions\MissingParameterException(
				"The Qualtrics username and/or API token was unspecified. These parameters must be passed to the constructor or stored in the cofig file."
			);
			
		// set the necessary credentials from the config files
		$this->username = $username;
		$this->token    = $token;
		$this->library  = $library;
		
		// set up our guzzle client
		$this->client = new Client([
			'base_url'        => $this->api_url,
			'request.options' => [
				'exceptions'      => false,
				'timeout'         => 0,
				'connect_timeout' => 0,
			],
			'defaults'        => [
				'query' => [
					'User'	  => $this->username,
					'Token'   => $this->token,
					'Version' => $this->version,
					'Format'  => 'JSON',
				],				
			],
		]);
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
	 * Adds a mock HTTP subscriber for testing purposes
	 *
	 * @return boolean True or false
	 */
	public function attachMock( $mock ) {
		$this->client->getEmitter()->attach( $mock );
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
		
		// set up the get request
		$response = $this->client->get( '', [ 'query' => array_merge( [ 'Request' => $request ], $params ) ] );
				
		// handle the response
		if ( '200' == $response->getStatusCode() ) {
			$data = $response->json();
			if ( "Success" == $data[ 'Meta' ][ 'Status' ] ) return json_decode( json_encode( $data[ 'Result' ] ) );
		} else {
			// unsuccessful response
			switch( $response->getStatusCode() ) {
				case '400':
				case '401':
				case '403':
				case '404':
					$data = $response->json();
					throw new QualtricsException( $data[ 'Meta' ][ 'ErrorMessage' ], $response->getStatusCode() );
					break;
				case '500':
			}
		}
	}
	
	/**
	 * Gets information about a user
	 *
	 * @return object Information about the user in question
	 */
	function getUserInfo() {
		// try to fetch  and return the results
		return $this->request( 'getUserInfo' );
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
	 * @param string Format Must be XML, CSV, or HTML, defaults to JSON
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
			'Format'    => 'JSON',
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
	 * @param string Format Must be XML, CSV, or HTML, defaults to JSON
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
	
		// return the response
		return $this->request( 'updateRecipient', $params );
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
	
		// return the response
		return $this->request( 'getLegacyResponseData', $params );
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
		return $this->request( 'getSurveys', $params );
	}

}