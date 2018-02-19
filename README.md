Advanced CSV Connector - Click And Mortar
=============================

Advanced CSV Connector is an extension of classic Akeneo CSV Connector. It allows to customize columns mapping with JSON as job parameter.

1. Installation
----------------------

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
