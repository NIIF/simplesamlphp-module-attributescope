Attributescope filter module
===========================

Filter to remove attribute values which are not properly scoped.

## Install module
You can install the module with composer:

    composer require niif/simplesamlphp-module-attributescope

### Authproc Filters

_config/config.php_

```
   authproc.sp = array(
       ...
       '60' => array(
            'class' => 'attributescope:FilterAttributes'
       ),
```