<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OpenAPIExtractor;

/**
 * A file that is uploaded with a multipart/form-data request.
 */
class UploadedFile {
	/**
	 * @param string $requestKey Key used to read the file from the request (`IRequest::getUploadedFile($requestKey)`)
	 * @param string $fieldName Name of the multipart field the client has to send, if it differs from the key (e.g. `files[]`)
	 * @param bool $multiple Whether the endpoint accepts several files in the field
	 * @param bool $required Whether the endpoint rejects requests without the file
	 */
	public function __construct(
		public string $requestKey,
		public string $fieldName,
		public bool $multiple = false,
		public bool $required = false,
	) {
	}

	/**
	 * @return array{type: 'string', format: 'binary'}|array{type: 'array', items: array{type: 'string', format: 'binary'}, minItems?: 1}
	 */
	public function toSchema(): array {
		$file = [
			'type' => 'string',
			'format' => 'binary',
		];

		if ($this->multiple) {
			$schema = [
				'type' => 'array',
				'items' => $file,
			];
			if ($this->required) {
				// An empty array would not send any file part
				$schema['minItems'] = 1;
			}

			return $schema;
		}

		return $file;
	}
}
