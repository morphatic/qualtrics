<?php

use Orchestra\Testbench\TestCase;
use GuzzleHtp\Client;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Message\Response;

class QualtricsTest extends TestCase {
	
	/**
	 * Qualtrics object
	 *
	 * This is an instance of the Qualtrics 
	 * class that we'll use for testing
	 *
	 * @var object $qtrx
	 */
	protected $qtrx;
	
	/**
	 * Mock object
	 *
	 * This is an instance of GuzzleHttp\Subscriber\Mock 
	 * used for mocking HTTP requests sent to the 
	 * Qualtrics API.
	 *
	 * @var object $mock
	 */
	protected $mock;
	
	/*
	|--------------------------------------------------------------------------
	| Environment setup
	|--------------------------------------------------------------------------
	*/
	protected function getPackageAliases() {
		return array( 'Qualtrics' => 'Morphatic\Qualtrics\Qualtrics' );
	}
	
	protected function getPackageProviders() {
		return array( 'Morphatic\Qualtrics\QualtricsServiceProvider' );
	}
	
	protected function getEnvironmentSetup( $app ) {
		// reset base path to point to our package's src directory
		$app[ 'path.base' ] = realpath( __DIR__ . '/../src' );
	}
	
	public function setUp() {
		parent::setUp();
		
		// create a new mock for responding to http requests
		$this->mock = new Mock;
		
		// initialize our test Qualtrics object
		$this->qtrx = new Qualtrics( 'test@user.com', 												 // fake user
									 '$2y$10$ooPG9s1lcwUGYv1nqeyNcO0ccYJf8hlhm5dJXy7xoamvgiczXHB7S', // fake token
									 'UR_5dS6LpHdKexqOjO'											 // fake library
		);
		
		// attach our mock to the client of the Qualtrics instance
		$this->qtrx->attachMock( $this->mock );
	}
	
	/*
	|--------------------------------------------------------------------------
	| Tests
	|--------------------------------------------------------------------------
	*/
	
	// Do our config parameters exist?
	public function testConfigHasUsernameProperty() {
		$username = Config::get( 'qualtrics::username' );
		$this->assertTrue( isset( $username ) );
	}

	public function testConfigHasTokenProperty() {
		$token = Config::get( 'qualtrics::token' );
		$this->assertTrue( isset( $token ) );
	}

	public function testConfigHasLibraryProperty() {
		$library = Config::get( 'qualtrics::library' );
		$this->assertTrue( isset( $library ) );
	}
	
	// Are the config parameters empty? (they should be)
	public function testConfigHasUsernamePropertyIsEmpty() {
		$username = Config::get( 'qualtrics::username' );
		$this->assertTrue( empty( $username ) );
	}

	public function testConfigHasTokenPropertyIsEmpty() {
		$token = Config::get( 'qualtrics::token' );
		$this->assertTrue( empty( $token ) );
	}

	public function testConfigHasLibraryPropertyIsEmpty() {
		$library = Config::get( 'qualtrics::library' );
		$this->assertTrue( empty( $library ) );
	}
	
	// Does our class exist?
	public function testClassExistsQualtrics() {
		$this->assertTrue( class_exists( 'Qualtrics' ) );
	}
	
	// Do our exception handlers exist?
	public function testQualtricsExceptionExists() {
		$this->assertTrue( class_exists( '\Morphatic\Qualtrics\Exceptions\QualtricsException' ) );
	}
		
	public function testMissingParameterExceptionExists() {
		$this->assertTrue( class_exists( '\Morphatic\Qualtrics\Exceptions\MissingParameterException' ) );
	}
	
	// Does our constructor work properly
	
	public function testConstructorCreatesAnInstanceOfQualtricsClass() {
		$this->assertInstanceOf( 'Qualtrics', $this->qtrx );
	}

	/**
	 * @expectedException	\Morphatic\Qualtrics\Exceptions\MissingParameterException
	 */
	public function testConstructorThrowsMissingParameterException() {
		$qtrx = new Qualtrics();
	}
	
	public function testAddRecipient() {
		$params = [ 'PanelID' => 'pppp', 'FirstName' => 'Test', 'LastName' => 'User', 'Email' => 'test@user.com' ];
		$values = $this->createMethodTest( 'addRecipient', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testCreateDistribution() {
		$params = [ 'SurveyID' => 'ssss', 'PanelID' => 'pppp', 'Description' => 'Test' ];
		$values = $this->createMethodTest( 'createDistribution', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testCreatePanel() {
		$params = [ 'Name' => 'Test' ];
		$values = $this->createMethodTest( 'createPanel', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testDeletePanel() {
		$params = [ 'PanelID' => 'pppp' ];
		$values = $this->createMethodTest( 'deletePanel', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testGetLegacyResponseData() {
		$params = [ 'SurveyID' => 'ssss' ];
		$values = $this->createMethodTest( 'getLegacyResponseData', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testGetPanel() {
		$params = [ 'PanelID' => 'pppp' ];
		$values = $this->createMethodTest( 'getPanel', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testGetPanelMemberCount() {
		$params = [ 'PanelID' => 'pppp' ];
		$values = $this->createMethodTest( 'getPanelMemberCount', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testGetPanels() {
		$params = [];
		$values = $this->createMethodTest( 'getPanels', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testGetRecipient() {
		$params = [ 'RecipientID' => 'rrrr' ];
		$values = $this->createMethodTest( 'getRecipient', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testGetResponseCountsBySurvey() {
		$params = [ 'StartDate' => '2014-08-14', 'EndDate' => '2014-08-14', 'SurveyID' => 'ssss' ];
		$values = $this->createMethodTest( 'getResponseCountsBySurvey', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testGetSurvey() {
		$params = [ 'SurveyID' => 'ssss' ];
		$values = $this->createMethodTest( 'getSurvey', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testGetSurveys() {
		$params = [];
		$values = $this->createMethodTest( 'getSurveys', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testGetUserInfo() {
		$params = [];
		$values = $this->createMethodTest( 'getUserInfo', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testImportPanel() {
		$params = [ 'Email' => 1, 'ColumnHeaders' => 0 ];
		$values = $this->createMethodTest( 'importPanel', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testRemoveRecipient() {
		$params = [ 'RecipientID' => 'rrrr', 'PanelID' => 'pppp' ];
		$values = $this->createMethodTest( 'removeRecipient', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testSendReminder() {
		$params = [ 'ParentEmailDistributionID' => 'aaaa', 'SendDate' => '2014-08-14 12:13:00', 
					'FromEmail' => 'test@user.com', 'FromName' => 'Test User', 'Subject' => 'Test', 
					'MessageID' => 'mmmm' ];
		$values = $this->createMethodTest( 'sendReminder', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testSendSurveyToIndividual() {
		$params = [ 'SurveyID' => 'ssss', 'SendDate' => '2014-08-14 12:13:00', 
					'FromEmail' => 'test@user.com', 'FromName' => 'Test User', 'Subject' => 'Test', 
					'MessageID' => 'mmmm', 'PanelID' => 'pppp', 'RecipientID' => 'rrrr' ];
		$values = $this->createMethodTest( 'sendSurveyToIndividual', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testSendSurveyToPanel() {
		$params = [ 'SurveyID' => 'ssss', 'SendDate' => '2014-08-14 12:13:00', 
					'FromEmail' => 'test@user.com', 'FromName' => 'Test User', 'Subject' => 'Test', 
					'MessageID' => 'mmmm', 'PanelID' => 'pppp' ];
		$values = $this->createMethodTest( 'sendSurveyToPanel', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	public function testUpdateRecipient() {
		$params = [ 'RecipientID' => 'rrrr' ];
		$values = $this->createMethodTest( 'updateRecipient', $params );
		$this->assertEquals( $values[ 0 ], $values[ 1 ] );
	}
	
	private function createMethodTest( $method, $params = null ) {
		$this->mock->addResponse(  __DIR__ . "/HttpRequests/$method.json" );
		$data = json_decode( file( __DIR__ . "/HttpRequests/$method.json" )[ 3 ] )->Result;
		return [ $data, $this->qtrx->{$method}( $params ) ];
	}
}