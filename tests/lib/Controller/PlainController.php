<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Notifications\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Response;

class PlainController extends Controller {
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/plain/ignored')]
	public function ignored(): Response {
		return new Response();
	}


	/**
	 * Route with manual scope to not get ignored
	 *
	 * @return Response<Http::STATUS_OK, array{}>
	 *
	 * 200: Response returned
	 */
	#[NoCSRFRequired]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	#[FrontpageRoute(verb: 'GET', url: '/plain/with-scope')]
	public function withScope(): Response {
		return new Response();
	}
}
