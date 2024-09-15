<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OpenAPIExtractor;

use Exception;
use PhpParser\Node\Expr;

class UnsupportedExprException extends Exception {
	public function __construct(
		public Expr $expr,
		public string $context,
	) {
		parent::__construct($this->context . ': Unable to parse Expr: ' . get_class($this->expr));
	}
}
