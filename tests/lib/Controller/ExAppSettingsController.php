<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Notifications\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ExAppRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;

class ExAppSettingsController extends OCSController {
	/**
	 * Route is in ex_app scope because of the attribute
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Personal settings updated
	 */
	#[ExAppRequired]
	public function exAppScopeAttribute(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route is in ex_app scope because of the override
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Personal settings updated
	 */
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_EX_APP)]
	public function exAppScopeOverride(): DataResponse {
		return new DataResponse();
	}
}
