# Magento 2 Importing library

Import library to create an array-interface for importing products/categories. Ho_Import is build on top
of Magento's internal Import/Export modules.

The goal of the library is to be a swiss army knife for importing products in Magento 2. Features include:

- Steam XML over HTTP and from disk
- Download files from HTTP(s)/FTP
- Map items from source file to Magento format
- Lot's of (RowModifiers)[RowModifier]
- Fixed importer core bugs

[ExampleProfile.php](docs/examples/basic/ExampleProfile.php)

## Goals
Performance: Since building imports is (really) hard and requires a lot of feedback loops to get your data right (change, check, change, check), it is absolutely essential that is as fast as possible. A developer can't work if he has to wait 10 minutes after each change. So only having to wait only a few seconds to be able to see what is going into Magento is essential.

Ease of use: The API should be clear that a developer is only limited by their knowledge of Magento it's self. No junior developer should have to thing about streaming files, performance and memory usage.

Extensible: It should be very easy to extend and customize a import

Maintainable: The library should have a stable API (currently not stable yet) so that we can upgrade imports that are build a year ago without having to worry that everything will break.


## Getting the abstraction right
With Ho_Import for Magento 1, we created a [custom DSL](https://github.com/ho-nl/magento1-Ho_Import/blob/master/docs/imports/product_multiple_sources.xml#L39-L209) to map external files to a Magento compatible format. This worked, but we soon discovered that we needed a lot of basic PHP functionality in the importer. We caught ourselves implementing PHP functionality in Ho_Import compatible wrappers...

The alternative was working bare with [Avs_FastSimpleImport](https://github.com/avstudnitz/AvS_FastSimpleImport) gave no abstraction other than, 'you can fill this array'. Although this was a huge leap forward from 'create your own csv file', it didn't offer any tools to make building imports easier more robust and faster.

Now, writing a new import library for Magento 2 and having to start from scratch, it was a good moment to create a new abstraction. Assuming that people who need to build imports at least know the basics of Magento 2 programming we can create an import that doesn't rely on 'nice abstractions', but does offer the tools to get an import quickly up and running.

1. Create a single class file to create a fully functional import. If the class is too complex, the developer can decide to spit the logic them selves.
2. Use RowModifiers to modify data and make it easy for other developers to create new

The core concept of the new import library is based around RowModifiers.

### What are RowModifiers?
A `RowModifier` can update items, add items, delete items, add new values, rewrite values, validate rows, etc.

Example usage of the `\Ho\Import\RowModifier\ItemMapper`

```PHP
$items = [ ... ]; //Array of all products

/** @var \Ho\Import\RowModifier\ItemMapper $itemMapper */
$itemMapper = $this->itemMapperFactory->create([
    'mapping' => [
        'store_view_code' => \Ho\Import\RowModifier\ItemMapper::FIELD_EMPTY,
        'sku' => function ($item) {
             return $item['ItemCode'];
         }
        'name' => function ($item) {
            return $item['NameField'] . $item['ColorCode'];
        }
    ]
]);
$itemMapper->setItems($items);
$itemMapper->process($items); //The items array is modified with new values.
```


RowModifies all inherit from `\Ho\Import\RowModifier\AbstractRowModifier`



## General Assumptions

- People writing imports are programmers or at least have basic programming knowledge.
- Magento's importer is limited and certainly doesn't *Just Work(tm)*, we need to build abstractions on top to be able to actually focus on the import instead of all the 'stuff' that comes with an import.

## Technical assumptions
- PHP's array format is memory efficient enough that it can hold all products needing to be imported in memory. e.g. 50k products requires more memory, but is usually ran on a beefy server.
-



## Contibutors

Here at H&O we've created many imports for clients, we have exstenbuild Ho_Import, core contributor to Avs_FastSimpleImport



that mapped source files to Magento compatible formats, but was never
intended to solve problems with url rewrites, creating configurables. All that functionality


This library builds on top of Magento's internal Import/Export module

## Console commands

### `ho:import:run profileName`

Run an import script directly (not recommended on live environments, might cause deadlocks).

### `cron:schedule jobName`

Schedule a job to run immediately.
