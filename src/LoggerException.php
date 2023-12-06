<?php

namespace OpenAPIExtractor;

use Exception;

class LoggerException extends Exception {
	public function __construct(
		public LoggerLevel $level,
		public string $context,
		public $message,
	) {
		parent::__construct($message);
	}
}
