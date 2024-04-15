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
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;

/**
 * @psalm-import-type NotificationsPushDevice from ResponseDefinitions
 * @psalm-import-type NotificationsNotification from ResponseDefinitions
 * @psalm-import-type NotificationsCollection from ResponseDefinitions
 */
class SettingsController extends OCSController {
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
	 * Route is ignored because of scope on the method but without `scope: ` name
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: OK
	 */
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	public function ignoreByUnnamedScopeOnMethod(): DataResponse {
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
	 * Route is referencing nested schemas
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
	 * Route is referencing a schema which is a list of schemas
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
	 * A route with a non negative integer
	 *
	 * @param non-negative-int $limit not negative
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function intParameterNonNegative(int $limit): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with a positive integer
	 *
	 * @param positive-int $limit positive
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function intParameterPositive(int $limit): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with a negative integer
	 *
	 * @param negative-int $limit negative
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function intParameterNegative(int $limit): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with a non positive integer
	 *
	 * @param non-positive-int $limit non positive
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function intParameterNonPositive(int $limit): DataResponse {
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
	public function listOfIntStringAndOneBool($weird): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with a list of 2 integers, 2 strings and 1 boolean
	 *
	 * @param 0|1|'yes'|'no'|true|false $weird Weird list
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function listOfIntStringAndAllBools($weird): DataResponse {
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
	 * @param bool $yesOrNo Boolean defaulting to false
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
	 * @param bool $yesOrNo Boolean defaulting to true
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function booleanParameterDefaultTrue(bool $yesOrNo = true): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with boolean or true
	 *
	 * @param bool|true $yesOrNo boolean or true
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function booleanTrueParameter(bool $yesOrNo): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with boolean or false
	 *
	 * @param bool|false $yesOrNo boolean or false
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function booleanFalseParameter(bool $yesOrNo): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with boolean or true or false
	 *
	 * @param bool|true|false $yesOrNo boolean or true or false
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function booleanTrueFalseParameter(bool $yesOrNo): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with true or false
	 *
	 * @param true|false $yesOrNo true or false
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function trueFalseParameter(bool $yesOrNo): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with string or 'test'
	 *
	 * @param string|'test' $value string or 'test'
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function stringValueParameter(string $value): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with int or 0
	 *
	 * @param int|0 $value int or 0
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function intValueParameter(int $value): DataResponse {
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

	/**
	 * A route with list
	 *
	 * @param list<string> $value Some array value
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function arrayListParameter(array $value = ['test']): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route with keyed array
	 *
	 * @param array<string, string> $value Some array value
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Admin settings updated
	 */
	public function arrayKeyedParameter(array $value = ['test' => 'abc']): DataResponse {
		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Route throws an OCS exception
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 * @throws OCSNotFoundException Description of 404 because we throw all the time
	 *
	 * 200: Admin settings updated
	 */
	public function throwingOCS(): DataResponse {
		throw new OCSNotFoundException();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Route throws an OCS exception
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 * @throws NotFoundException Description of 404 because we throw all the time
	 *
	 * 200: Admin settings updated
	 */
	public function throwingOther(): DataResponse {
		throw new NotFoundException();
	}

	/**
	 * A route 204 response
	 *
	 * @return DataResponse<Http::STATUS_NO_CONTENT, array<empty>, array{X-Custom: string}>
	 *
	 * 204: No settings
	 */
	public function empty204(): DataResponse {
		return new DataResponse();
	}

	/**
	 * A route 304 response
	 *
	 * @return DataResponse<Http::STATUS_NOT_MODIFIED, array<empty>, array{}>
	 *
	 * 304: No settings
	 */
	public function empty304(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route with password confirmation annotation
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 * @PasswordConfirmationRequired
	 *
	 * 200: OK
	 */
	public function passwordConfirmationAnnotation(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route with password confirmation attribute
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: OK
	 */
	#[PasswordConfirmationRequired]
	public function passwordConfirmationAttribute(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route with oneOf
	 *
	 * @return DataResponse<Http::STATUS_OK, string|int|double|bool, array{}>
	 *
	 * 200: OK
	 */
	#[PasswordConfirmationRequired]
	public function oneOf(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route with anyOf
	 *
	 * @return DataResponse<Http::STATUS_OK, array{test: string}|array{test: string, abc: int}, array{}>|DataResponse<Http::STATUS_CREATED, array{foobar: string}|array{disco: string, abc: int}|array{test: string, abc: int}, array{}>|DataResponse<Http::STATUS_ACCEPTED, float|numeric, array{}>|DataResponse<Http::STATUS_RESET_CONTENT, int|double, array{}>
	 *
	 * 200: OK
	 * 201: CREATED
	 * 202: ACCEPTED
	 * 205: RESET CONTENT
	 */
	#[PasswordConfirmationRequired]
	public function anyOf(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route with float and double
	 *
	 * @return DataResponse<Http::STATUS_OK, float, array{}>|DataResponse<Http::STATUS_CREATED, double, array{}>
	 *
	 * 200: OK
	 * 201: CREATED
	 */
	#[PasswordConfirmationRequired]
	public function floatDouble(): DataResponse {
		return new DataResponse();
	}

	/**
	 * Route with empty array
	 *
	 * @return DataResponse<Http::STATUS_OK, array{test: array<empty>}, array{}>
	 *
	 * 200: OK
	 */
	#[PasswordConfirmationRequired]
	public function emptyArray(): DataResponse {
		return new DataResponse();
	}
}
