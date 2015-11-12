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
        // 49 => array('class' => 'core:AttributeMap', 'oid2name'),
        50 => array(
            'class' => 'attributescope:FilterAttributes',
            // Default scoped attributes. You can override by your attributes.
            // 'scopedattributes' => array('eduPersonPrincipalName', 'eduPersonScopedAffiliation'),
       ),
```