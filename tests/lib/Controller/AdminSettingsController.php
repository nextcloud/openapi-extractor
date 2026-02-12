<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Notifications\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;

class AdminSettingsController extends OCSController {
	/**
	 * Route is only in the admin scope because there is no "NoAdminRequired" annotation or attribute
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 *
	 * 200: Personal settings updated
	 */
	public function adminScopeImplicitFromAdminRequired(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route is in the default scope because the method overwrites with the Attribute
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 *
	 * 200: Personal settings updated
	 */
	#[OpenAPI]
	public function movedToDefaultScope(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route in default scope with tags
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 *
	 * 200: Personal settings updated
	 */
	#[OpenAPI(tags: ['settings', 'admin-settings'])]
	public function movedToSettingsTag(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route in default scope with tags but without named parameters on the attribute
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 *
	 * 200: Personal settings updated
	 */
	#[OpenAPI(OpenAPI::SCOPE_ADMINISTRATION, ['settings', 'admin-settings'])]
	public function movedToSettingsTagUnnamed(): DataResponse {
		return new DataResponse();
	}

	public const ALSO_OPTIONAL = 1;

	/**
	 * OCS Route with attribute
	 *
	 * @param int $option1 This is optional with magic number
	 * @param int $option2 This is optional with constant
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 *
	 * 200: Success
	 */
	#[ApiRoute(verb: 'POST', url: '/optional-parameters')]
	public function optionalParameters(
		int $option1 = 0,
		int $option2 = self::ALSO_OPTIONAL,
	) {
		return new DataResponse();
	}

}
