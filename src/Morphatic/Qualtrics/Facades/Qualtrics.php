<?php
namespace Morphatic\Qualtrics\Facades;

use Illuminate\Support\Facades\Facade;

class Qualtrics extends Facade {
	
	/**
	 * Returns the registered name of the package.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor() {
		return 'qualtrics';
	}
	
}