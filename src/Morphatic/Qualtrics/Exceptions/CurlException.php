<?php
/**
 * CurlException.php
 */

namespace Morphatic\Qualtrics\Exceptions;

/**
 * CurlException class
 *
 * This exception is thrown when a curl object encounters
 * an error while being executed, e.g. request timeout.
 */
class CurlException extends \UnexpectedValueException {}