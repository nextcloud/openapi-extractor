<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Notifications\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;

class ReturnArraysController extends OCSController {
	/**
	 * Route with array using string keys
	 *
	 * @return DataResponse<Http::STATUS_OK, non-empty-array<string, mixed>|\stdClass, array{}>
	 *
	 * 200: OK
	 */
	public function stringArray(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route with array using non-empty-string keys
	 *
	 * @return DataResponse<Http::STATUS_OK, non-empty-array<non-empty-string, mixed>|\stdClass, array{}>
	 *
	 * 200: OK
	 */
	public function nonEmptyStringArray(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route with array using lowercase-string keys
	 *
	 * @return DataResponse<Http::STATUS_OK, non-empty-array<lowercase-string, mixed>|\stdClass, array{}>
	 *
	 * 200: OK
	 */
	public function lowercaseStringArray(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route with array using non-empty-lowercase-string keys
	 *
	 * @return DataResponse<Http::STATUS_OK, non-empty-array<non-empty-lowercase-string, mixed>|\stdClass, array{}>
	 *
	 * 200: OK
	 */
	public function nonEmptyLowercaseStringArray(): DataResponse {
		return new DataResponse();
	}
}
