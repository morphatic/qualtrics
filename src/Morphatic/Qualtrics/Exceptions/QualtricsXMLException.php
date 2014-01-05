<?php
/**
 * QualtricsXMLException.php
 */

namespace Morphatic\Qualtrics\Exceptions;

/**
 * QualtricsXMLException class
 *
 * This exception is thrown when the XML returned by Qualtrics
 * cannot be parsed correctly.
 */
class QualtricsXMLException extends \UnexpectedValueException {}