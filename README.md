# Advanced CSV Connector - C&M

Advanced CSV Connector is an extension of classic Akeneo CSV Connector. It allows to customize columns mapping on import or export.

Made with :blue_heart: by C&M

## Versions

| **Bundle version**  | **Akeneo version** |
| ------------- | ------------- |
| v1.5.*  | v3.1.*  |
| v1.4.*  | v2.3.*  |
| v1.3.*  | v2.1.*  |

## Installation

### Download the Bundle

```console
$ composer require clickandmortar/advanced-csv-connector-bundle
```

### Enable the Bundle

Enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new Pim\Bundle\CustomEntityBundle\PimCustomEntityBundle(),
            new ClickAndMortar\AdvancedEnrichBundle\ClickAndMortarAdvancedEnrichBundle(),
            new ClickAndMortar\AdvancedCsvConnectorBundle\ClickAndMortarAdvancedCsvConnectorBundle(),
        ];

        // ...
    }

    // ...
}
```

Update your `app/config/routing.yml` file to enable custom entities:

```
pim_customentity:
        prefix: /reference-data
        resource: "@PimCustomEntityBundle/Resources/config/routing.yml"
```

And finally update your database:

```
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
* `Transformation`: Method name (can be a custom method in your extended `ImportHelper`) to update value after mapping. Example: Uppercase, lowercase, ...
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
* `Transformation`: Method name (can be a custom method in your extended `ExportHelper`) to update value after mapping. Example: Uppercase, lowercase, ...
* `Utiliser le libellé`: Boolean to get the label associated to the code given (for attribute options or custom entities)
* `Langue`: Select a specific locale for the label to export (linked to `Utiliser le libellé` column)
* `Longueur max.`: Integer use to shorten attribute value if necessary
* `Valeur par défaut`: Default value for column if empty attribute value
* `Supprimer`: Click on this cell to delete mapping line

Once mapping is saved, go to `Exports` part and create a new job with type `Export des produits avancé (CSV)`.
After job creation, go to edition mode and update `Mapping` parameter in global parameters tab.
