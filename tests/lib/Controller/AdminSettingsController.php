<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021, Julien Barnoin <julien@barnoin.com>
 *
 * @author Julien Barnoin <julien@barnoin.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Personal settings updated
	 */
	public function adminScopeImplicitFromAdminRequired(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route is in the default scope because the method overwrites with the Attribute
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
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
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
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
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Personal settings updated
	 */
	#[OpenAPI(OpenAPI::SCOPE_ADMINISTRATION, ['settings', 'admin-settings'])]
	public function movedToSettingsTagUnnamed(): DataResponse {
		return new DataResponse();
	}
}
