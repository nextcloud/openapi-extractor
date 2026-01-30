<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Notifications\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\OCSController;

class OpenMetricsController extends OCSController {
	/**
	 * Use StreamTraversableResponse response type
	 *
	 * @return StreamTraversableResponse<Http::STATUS_OK, list<empty>, array{}>
	 *
	 * 200: Export OK
	 */
	public function export(): StreamTraversableResponse {
		return new StreamTraversableResponse($this->generator());
	}

	private function generator(): \Generator {
		yield from [];
	}
}
