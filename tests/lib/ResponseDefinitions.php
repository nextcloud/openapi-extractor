<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Notifications;

use OCA\Notifications\Controller\AdminSettingsController;

/**
 * @psalm-type NotificationsItem = array{
 *     label: string,
 *     link: string,
 *     type: string,
 *     primary: bool,
 *     class: class-string<AdminSettingsController>,
 * }
 *
 * @psalm-type NotificationsCollection = list<NotificationsItem>
 *
 * @psalm-type NotificationsNotificationAction = array{
 *     label: string,
 *     link: string,
 *     type: string,
 *     primary: bool,
 * }
 *
 * @psalm-type NotificationsNotification = array{
 *     notification_id: int,
 *     app: string,
 *     user: string,
 *     datetime: string,
 *     object_type: string,
 *     object_id: string,
 *     subject: string,
 *     message: string,
 *     link: string,
 *     actions: list<NotificationsNotificationAction>,
 *     subjectRich?: string,
 *     subjectRichParameters?: array<string, mixed>,
 *     messageRich?: string,
 *     messageRichParameters?: array<string, mixed>,
 *     icon?: string,
 *     shouldNotify?: bool,
 *     nonEmptyList: non-empty-list<string>,
 * }
 *
 * @psalm-type NotificationsPushDeviceBase = array{
 *     deviceIdentifier: string,
 * }
 *
 * @psalm-type NotificationsPushDevice = NotificationsPushDeviceBase&array{
 *     publicKey: string,
 *     signature: string,
 * }
 *
 * @psalm-type NotificationsRequestProperty = array{
 *     // A comment.
 *     publicKey: string,
 * 	   // A comment with a link: https://example.com.
 *     signature: string,
 *     // A comment.
 *     // Another comment.
 *     multipleComments: string,
 *     ref1: ?NotificationsPushDevice,
 *     ref2: null|NotificationsPushDevice,
 *     ref3: string|null|NotificationsPushDevice,
 * }
 */
class ResponseDefinitions {
}
