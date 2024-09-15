<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# openapi-extractor

## Installation

```sh
composer require --dev nextcloud/openapi-extractor
```

To avoid dependency and PHP version conflicts it is best to install the package to vendor-bin using https://github.com/bamarni/composer-bin-plugin instead.

## Create a CI workflow to check the specifications are up-to-date

The Workflow template repository has a template available: https://github.com/nextcloud/.github/blob/master/workflow-templates/openapi.yml

Afterward in your repository settings set the OpenAPI workflow to be required for merging pull requests.

## Usage

Checkout the OpenAPI tutorial at https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-openapi.html to see how you can use openapi-extractor.

### 🐢 Performance

Make sure that you have xdebug turned off when generating OpenAPI specs, otherwise it can take multiple minutes instead of seconds.
