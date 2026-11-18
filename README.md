<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# openapi-extractor

[![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/openapi-extractor)](https://api.reuse.software/info/github.com/nextcloud/openapi-extractor)

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

### Uploaded files

Files read with `$this->request->getUploadedFile('name')` are documented as optional binary properties of a `multipart/form-data` request body (POST routes only), next to the `application/json` body. Use the `RequestFile` attribute when the code alone is not enough:

```php
#[RequestFile(requestKey: 'files', fieldName: 'files[]', multiple: true, required: true, indirect: true)]
```

- `multiple: true` documents several files. Without the attribute, a file is always documented as a single file.
- `required: true` marks the file as required. The JSON body is then not documented, because it can not carry a file.
- `indirect: true` is needed when the file is read outside of the controller method.
- An optional body parameter with the same name as an uploaded file field (ignoring a trailing `[]`) is only documented in the JSON body. A required one with the exact same name is an error.

Not supported: requiring "at least one of several files", fields that only exist in the multipart body, and both `file` and `file[]` for the same request key.

### 🐢 Performance

Make sure that you have xdebug turned off when generating OpenAPI specs, otherwise it can take multiple minutes instead of seconds.
