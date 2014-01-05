<?php
/**
 * QualtricsException.php
 */

namespace Morphatic\Qualtrics\Exceptions;

/**
 * QualtricsException class
 *
 * This exception is thrown when the Qualtrics API returns
 * an error, e.g. when a username/token combination lacks 
 * permission to call a particular function.
 */
class QualtricsException extends \UnexpectedValueException {}