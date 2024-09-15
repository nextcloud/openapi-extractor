<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Notifications\Controller;

use OCA\Notifications\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;

/**
 * @psalm-import-type NotificationsPushDevice from ResponseDefinitions
 * @psalm-import-type NotificationsNotification from ResponseDefinitions
 * @psalm-import-type NotificationsCollection from ResponseDefinitions
 * @psalm-import-type NotificationsRequestProperty from ResponseDefinitions
 */
#[OpenAPI(scope: OpenAPI::SCOPE_FEDERATION)]
class FederationController extends OCSController {

	/**
	 * @NoAdminRequired
	 *
	 * Route is in federation scope as per controller scope
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
	 * Route is only in the default scope (moved from federation)
	 *
	 * @param NotificationsRequestProperty $property Property
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: Personal settings updated
	 */
	#[OpenAPI]
	public function movedToDefaultScope(array $property): DataResponse {
		return new DataResponse();
	}
}
