<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OpenAPIExtractor;

class ControllerMethodParameter {
	public function __construct(
		string $context,
		array $definitions,
		public string $name,
		public OpenApiType $type,
	) {
	}
}
