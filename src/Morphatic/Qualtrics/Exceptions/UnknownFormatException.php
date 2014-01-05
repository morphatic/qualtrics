<?php
/**
 * UnknownFormatException.php
 */

namespace Morphatic\Qualtrics\Exceptions;

/**
 * UnknownFormatException class
 *
 * This exception is thrown when Qualtrics returns a response that
 * is not among the known types listed by the content-type header.
 */
class UnknownFormatException extends \UnexpectedValueException {}