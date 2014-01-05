<?php
/**
 * QualtricsServiceProvider.php
 */

namespace Morphatic\Qualtrics;

use Illuminate\Support\ServiceProvider;

/**
 * QualtricsServiceProvider class
 *
 * This class registers the Qualtrics package and all of its
 * exceptions with the Laravel 4 framework.
 */
class QualtricsServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package( 'morphatic/qualtrics' );
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// bind the Qualtrics class for use with a facade
		$this->app->bind( 'qualtrics', function() { return new Qualtrics; } );
		
		// register exception handlers
		$this->app->error( function( Exceptions\QualtricsException $e ) {
			return $e->getCode() . ': ' . $e->getMessage();
		});
		$this->app->error( function( Exceptions\QualtricsXMLException $e ) {
			return $e->getCode() . ': ' . $e->getMessage();
		});
		$this->app->error( function( Exceptions\CurlException $e ) {
			return $e->getCode() . ': ' . $e->getMessage();
		});
		$this->app->error( function( Exceptions\CurlNotInstalledException $e ) {
			return $e->getMessage();
		});
		$this->app->error( function( Exceptions\UnknownFormatException $e ) {
			return $e->getMessage();
		});
		$this->app->error( function( Exceptions\MissingParameterException $e ) {
			return $e->getCode() . ': ' . $e->getMessage();
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}