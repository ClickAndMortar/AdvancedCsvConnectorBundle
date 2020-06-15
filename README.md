# Advanced CSV Connector - C&M

Advanced CSV Connector is an extension of classic Akeneo CSV Connector. It allows to customize columns mapping on import or export.

Made with :blue_heart: by C&M

## Versions

| **Bundle version**  | **Akeneo version** |
| ------------- | ------------- |
| v1.8.*  | v4.0.*  |
| v1.7.*  | v3.2.* (EE)  |
| v1.6.*  | v3.1.* / v3.2.*  |
| v1.5.*  | v3.1.* / v3.2.*  |
| v1.4.*  | v2.3.*  |
| v1.3.*  | v2.1.*  |

## Requirements

You need to install `php-lua` package for usage of LUA scripts to update your values dynamically during import or export.
For LUA scripts available functions and libraries have been limited for security reasons. You can use:

* string
* math
* ipairs
* load
* next
* pairs
* rawequal
* rawgetwget
* rawlen
* rawset
* select
* tonumber
* tostring
* type


## Installation

### Download the Bundle

```console
$ composer require "clickandmortar/advanced-csv-connector-bundle":"<version-wanted>.*"
```

Example for last version:

```console
$ composer require "clickandmortar/advanced-csv-connector-bundle":"1.8.*"
```


### Enable the Bundle

Enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
<?php

return [
    // ...
    Pim\Bundle\CustomEntityBundle\PimCustomEntityBundle::class => ['all' => true],
    ClickAndMortar\AdvancedCsvConnectorBundle\ClickAndMortarAdvancedCsvConnectorBundle::class => ['all' => true]
];
```

Update your `app/config/routing.yml` file:

```
pim_customentity:
        prefix: /reference-data
        resource: "@PimCustomEntityBundle/Resources/config/routing.yml"
        
candm_advanced_csv_connector:
    prefix: /candm-advanced-csv-connector
    resource: "@ClickAndMortarAdvancedCsvConnectorBundle/Resources/config/routing.yml"
```

And finally clear cache and update database:

```
rm -rf var/cache/*
php bin/console --env=prod pim:installer:assets --symlink --clean
yarn run webpack
php bin/console doctrine:schema:update --force
```

### Configuration

You need to add some parameters to your `app/config/parameters.yml` file:

* `media_url_prefix`: This parameter is used in advanced export processor to generate clean url's from PIM visuals (value example: `https://www.my-pim.com/files/catalog/`)

## Usage

### Import

To create a new import mapping, go to `Référenciel / Mappings d'import` and click on `Create` top right button.
You can add as many mapping lines as you want by clicking on `Ajouter une ligne`.

Some explanations for table columns:

* `Attribut` (mandatory): Attribute code in your Akeneo project (you can use suffixes like `-fr_FR` or `-EUR` for locales, channels, currencies, ...)
* `Nom de la colonne` (mandatory): Column name in your file to import
* `Transformation`: LUA script name to update value after mapping. Example: Uppercase, lowercase, ... (you can create a new LUA script under `Référenciel / Scripts LUA`)
* `Valeur par défaut`: Default value for attribute if empty data in file
* `Identifiant` (mandatory):  Used to defined main identifier attribute of product
* `Uniquement à la création`: Set attribute value only if product is new (checked with `identifier` attribute)
* `Effacer si null`: Remove key from item mapping if value is null
* `Supprimer`: Click on this cell to delete mapping line

Once mapping is saved, go to `Imports` part and create a new job with type `Import des produits avancé (CSV)`.
After job creation, go to edition mode and update `Mapping` parameter in global parameters tab.

### Export

To create a new export mapping, go to `Référenciel / Mappings d'export` and click on `Create` top right button.
You can add as many mapping lines as you want by clicking on `Ajouter une ligne`.

Some explanations for table columns:

* `Attribut` (mandatory): Attribute code in your Akeneo project (you can use suffixes like `-fr_FR` or `-EUR` for locales, channels, currencies, ...)
* `Nom de la colonne` (mandatory): Column name in your file to export
* `Valeur forcée`: Force a value (erase given attribute value from Akeneo)
* `Transformation`: LUA script name to update value after mapping. Example: Uppercase, lowercase, ... (you can create a new LUA script under `Référenciel / Scripts LUA`)
* `Utiliser le libellé`: Boolean to get the label associated to the code given (for attribute options or custom entities)
* `Langue`: Select a specific locale for the label to export (linked to `Utiliser le libellé` column)
* `Longueur max.`: Integer use to shorten attribute value if necessary
* `Valeur par défaut`: Default value for column if empty attribute value
* `Supprimer`: Click on this cell to delete mapping line

Once mapping is saved, go to `Exports` part and create a new job with type `Export des produits avancé (CSV)`.
After job creation, go to edition mode and update `Mapping` parameter in global parameters tab.