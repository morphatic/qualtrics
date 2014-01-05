<?php

use Orchestra\Testbench\TestCase;

class QualtricsTest extends TestCase {
	
	/*
	|--------------------------------------------------------------------------
	| Environment setup
	|--------------------------------------------------------------------------
	*/
	protected function getPackageAliases() {
		return [ 'Qualtrics' => 'Morphatic\Qualtrics\Facades\Qualtrics' ];
	}
	
	protected function getPackageproviders() {
		return [
			'Morphatic\Qualtrics\QualtricsServiceProvider',
		];
	}
	
	protected function getEnvironmentSetup( $app ) {
		// reset base path to point to our package's src directory
		$app[ 'path.base' ] = realpath( __DIR__ . '/../src' );
	}
	
	public function setUp() {
		parent::setUp();
	}
	
	/*
	|--------------------------------------------------------------------------
	| Tests
	|--------------------------------------------------------------------------
	*/
	public function testConfig() {
	}
	
	public function testPermissions() {
	}
}