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

## Create a CI workflow to check the specifications are up-to-date

The Workflow template repository has a template available: https://github.com/nextcloud/.github/blob/master/workflow-templates/openapi.yml

Afterward in your repository settings set the OpenAPI workflow to be required for merging pull requests.

## Usage

Checkout the OpenAPI tutorial at https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-openapi.html to see how you can use openapi-extractor.

### 🐢 Performance

Make sure that you have xdebug turned off when generating OpenAPI specs, otherwise it can take multiple minutes instead of seconds.
