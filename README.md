# H&O Importing library

## Installation

```BASH
composer config repositories.honl/honl/magento2-import vcs git@github.com:ho-nl/magento2-Ho_Import.git
composer require honl/honl/magento2-import
```


## Console commands

### `ho:import:run profileName`

Run an import script directly (not recommended on live environments, might cause deadlocks).

### `cron:schedule jobName`

Schedule a job to run immediately.
