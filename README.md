# openapi-extractor

## Installation

This tool should be added as a dev dependency to the `composer.json` of your app (or in your `vendor-bin`) like this:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/nextcloud/openapi-extractor"
        }
    ],
    "require-dev": {
        "nextcloud/openapi-extractor": "dev-main"
    }
}
```

## Create a CI workflow

Put the following at `.github/workflows/openapi.yml`:

```yaml
name: OpenAPI

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  openapi:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout
        uses: actions/checkout@v3

      - name: Set up php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: xml
          coverage: none
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

      - name: Composer install
        run: composer i

      - name: OpenAPI checker
        run: |
          composer exec generate-spec
          if [ -n "$(git status --porcelain openapi.json)" ]; then
            git diff
            exit 1
          fi
```

Afterward in your repository settings set the OpenAPI workflow to be required for merging pull requests.

## Usage

Checkout the OpenAPI tutorial at https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-openapi.html to see how you can use openapi-extractor.

### 🐢 Performance

Make sure that you have xdebug turned off when generating OpenAPI specs, otherwise it can take multiple minutes instead of seconds.
