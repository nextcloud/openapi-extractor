<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Notifications\Notification;

/**
 * The priority of a notification
 *
 * Declared in a sub-namespace/sub-directory to confirm that enums are resolved
 * by mapping their namespace to a file path instead of relying on a directory scan.
 */
enum NotificationPriority: int {
	case Low = 0;
	case Normal = 1;
	case High = 2;
}
