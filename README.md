# Advanced CSV Connector - Click And Mortar

Advanced CSV Connector is an extension of classic Akeneo CSV Connector. It allows to customize columns mapping on import or export with JSON as job parameter.

## Installation

Add package your **`composer.json`** file:
```javascript
"require": {
    ...
    "clickandmortar/advanced-csv-connector-bundle": "^1.0"
    ...
}
```

Launch `composer update` to add bundle to your project:
```bash
composer update clickandmortar/advanced-csv-connector-bundle
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
            "dataCode": "trancheAge"
        },
        {
            "attributeCode": "life_cycle",
            "dataCode": "idCycleVie"
        },
        {
            "attributeCode": "price-EUR",
            "dataCode": "prix"
        }
    ]
}
```

Mapping explanation:

* `attributes` (mandatory): This is the default key that must contain mapping for all output/input attributes
* `attributeCode` (mandatory): The attribute code in your Akeneo project
* `dataCode` (mandatory): The column name in your file
* `callback`: The method name in your import helper to transform data from CSV file

### Export

TODO