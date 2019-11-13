# Magento 2 Safecharge Payments Module

---

## ✓ Install via composer (recommended)
Run the following command under your Magento 2 root dir:

```
composer require safecharge/magento2-module-safecharge
php bin/magento maintenance:enable
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento maintenance:disable
php bin/magento cache:flush
```

## Install manually under app/code
Download & place the contents of this repository under {YOUR-MAGENTO2-ROOT-DIR}/app/code/Safecharge/Safecharge  
Then, run the following commands under your Magento 2 root dir:
```
php bin/magento maintenance:enable
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento maintenance:disable
php bin/magento cache:flush
```

---

https://www.safecharge.com/

© 2007 - 2018 SafeCharge International Group Limited.
All rights reserved.

![Safecharge Logo](https://www.safecharge.com/docs/API/images/Icons_SC_logo.svg)
