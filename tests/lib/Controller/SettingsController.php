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
use OCP\AppFramework\Http\Attribute\IgnoreOpenAPI;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;

/**
 * @psalm-import-type NotificationsPushDevice from ResponseDefinitions
 * @psalm-import-type NotificationsNotification from ResponseDefinitions
 * @psalm-import-type NotificationsCollection from ResponseDefinitions
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
	 * Route is ignored because of IgnoreOpenAPI attribute on the method
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: OK
	 */
	#[IgnoreOpenAPI]
	public function ignoreByDeprecatedAttributeOnMethod(): DataResponse {
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
	public function ignoreByScopeOnMethod(): DataResponse {
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
	public function movedToDefaultScope(): DataResponse {
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
	public function movedToAdminScope(): DataResponse {
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
	public function defaultAndAdminScope(): DataResponse {
		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Route is ignored because of scope on the controller
	 *
	 * @return DataResponse<Http::STATUS_OK, list<NotificationsNotification>, array{}>
	 *
	 * 200: OK
	 */
	public function nestedSchemas(): DataResponse {
		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Route is ignored because of scope on the controller
	 *
	 * @return DataResponse<Http::STATUS_OK, NotificationsCollection, array{}>
	 *
	 * 200: OK
	 */
	public function listSchemas(): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with a limited set of possible integers
	 *
	 * @param 1|2|3|4|5|6|7|8|9|10 $limit Maximum number of objects
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function listOfIntParameters(int $limit): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with a min and max integers
	 *
	 * @param int<5, 10> $limit Between 5 and 10
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function intParameterWithMinAndMax(int $limit): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with a min integers
	 *
	 * @param int<5, max> $limit At least 5
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function intParameterWithMin(int $limit): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with a max integers
	 *
	 * @param int<min, 10> $limit At most 10
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function intParameterWithMax(int $limit): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with a list of 2 integers, 2 strings and 1 boolean
	 *
	 * @param 0|1|'yes'|'no'|true $weird Weird list
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function listOfIntStringAndBool($weird): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with required boolean
	 *
	 * @param bool $yesOrNo Boolean required
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function booleanParameterRequired(bool $yesOrNo): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with boolean defaulting to false
	 *
	 * @param bool $yesOrNo Booleandefaulting to false
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function booleanParameterDefaultFalse(bool $yesOrNo = false): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with boolean defaulting to true
	 *
	 * @param bool $yesOrNo Booleandefaulting to true
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function booleanParameterDefaultTrue(bool $yesOrNo = true): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with numeric
	 *
	 * @param numeric $value Some numeric value
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function numericParameter(mixed $value): DataResponse {
		return new DataResponse();
	}
}
