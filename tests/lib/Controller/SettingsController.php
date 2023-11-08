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

use OCA\Notifications\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;

/**
 * @psalm-import-type NotificationsPushDevice from ResponseDefinitions
 */
#[OpenAPI(scope: OpenAPI::SCOPE_FEDERATION)]
class SettingsController extends OCSController {

	/**
	 * @NoAdminRequired
	 *
	 * Route is ignored because of scope on the controller
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: OK
	 */
	public function federationByController(): DataResponse {
		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Route is ignored because of scope on the method
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: OK
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	public function ignoreByMethod(): DataResponse {
		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Route is only in the default scope
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Personal settings updated
	 */
	#[OpenAPI]
	public function defaultScope(): DataResponse {
		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Route is only in the admin scope due to defined scope
	 *
	 * @return DataResponse<Http::STATUS_OK, NotificationsPushDevice, array{}>
	 *
	 * 200: Admin settings updated
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	public function adminScope(): DataResponse {
		return new DataResponse($this->createNotificationsPushDevice());
	}

	/**
	 * @return NotificationsPushDevice
	 */
	protected function createNotificationsPushDevice(): array {
		return [
			'publicKey' => 'publicKey',
			'deviceIdentifier' => 'deviceIdentifier',
			'signature' => 'signature',
		];
	}

	/**
	 * @NoAdminRequired
	 *
	 * Route is in admin and default scope
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	#[OpenAPI]
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	public function doubleScope(): DataResponse {
		return new DataResponse();
	}
}
