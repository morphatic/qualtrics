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
		// register our class
		$this->app[ 'qualtrics' ] = $this->app->share( function( $app ) { return new Qualtrics; } );
		
		// create an alias so users don't have to add this entry to their app.config file
		$this->app->booting( function() {
			$loader = \Illuminate\Foundation\AliasLoader::getInstance();
			$loader->alias( 'Qualtrics', 'Morphatic\Qualtrics\Qualtrics' );
		});
		
		// register exception handlers
		$this->app->error( function( Exceptions\QualtricsException $e ) {
			return $e->getCode() . ': ' . $e->getMessage();
		});
		$this->app->error( function( Exceptions\QualtricsXMLException $e ) {
			return $e->getCode() . ': ' . $e->getMessage();
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
		return array( 'qualtrics' );
	}

}