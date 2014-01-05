<?php
/**
 * MissingParameterException.php
 */

namespace Morphatic\Qualtrics\Exceptions;

/**
 * MissingParameterException class
 *
 * This exception is thrown when the user has not submitted
 * all of the necessary parameters for a successful call to 
 * a particular Qualtrics function.
 */
class MissingParameterException extends \UnexpectedValueException {}