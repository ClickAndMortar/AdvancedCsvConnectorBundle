# Advanced CSV Connector - Click And Mortar

Advanced CSV Connector is an extension of classic Akeneo CSV Connector. It allows to customize columns mapping on import or export with JSON as job parameter.

Made by :heart: by C&M

## Installation

Add package with composer:
```bash
composer require clickandmortar/advanced-csv-connector-bundle "^1.0"
```

Add bundle in your **`app/AppKernel.php`** file:
```php
$bundles = array(
            ...
            new ClickAndMortar\AdvancedCsvConnectorBundle\ClickAndMortarAdvancedCsvConnectorBundle(),
        );
```

## Usage

### Import

To create a new import job based on Advanced CSV connector, go to `Imports` part and create a new job with type `Import des produits avancé (CSV)`.
After job creation, go to edition mode and update `Mapping` parameter in global parameters tab.

Import mapping example:

```json
{
    "attributes": [
        {
            "attributeCode": "ean_code",
            "dataCode": "codeEan"
        },
        {
            "attributeCode": "lens_height",
            "dataCode": "hauteurVerre",
            "callback": "setMetricUnitAsSuffix"
        },
        {
            "attributeCode": "universe",
            "dataCode": "style"
        },
        {
            "attributeCode": "age_range",
            "dataCode": "trancheAge",
            "normalizerCallback": "getAgeRange"
        },
        {
            "attributeCode": "life_cycle",
            "dataCode": "idCycleVie",
            "defaultValue": "1"
        },
        {
            "attributeCode": "price-EUR",
            "dataCode": "prix"
        }
    ],
    "normalizers": [
            {
                "code": "getAgeRange",
                "values": [
                    {
                        "normalizedValue": "0-18",
                        "originalValues": [
                            "5",
                            "12"
                        ]
                    },
                    {
                        "normalizedValue": "18-35",
                        "originalValues": [
                            "19",
                            "26"
                        ]
                    },
                    {
                        "normalizedValue": "35-50",
                        "originalValues": [
                            "38"
                        ]
                    }
            }
        ]
}
```

Mapping explanation:

* `attributes` (mandatory): This is the default key that must contain mapping for all output/input attributes
* `attributeCode` (mandatory): The attribute code in your Akeneo project
* `dataCode` (mandatory): The column name in your file
* `callback`: The method name in your import helper to transform data from CSV file
* `defaultValue`: Default value for attribute if empty data in file

### Export

TODO