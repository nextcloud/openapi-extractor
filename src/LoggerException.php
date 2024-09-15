<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OpenAPIExtractor;

use Exception;

class LoggerException extends Exception {
	/** @psalm-suppress MissingParamType False-positive */
	public function __construct(
		public LoggerLevel $level,
		public string $context,
		public $message,
	) {
		parent::__construct($message);
	}

	public function __toString(): string {
		return $this->level->value . ': ' . $this->context . ': ' . $this->message;
	}
}
