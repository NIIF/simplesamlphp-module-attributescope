Attributescope filter module
============================

## Filter to remove
* all attributes if there is no `shibmd:Scope` value for the IdP
* attribute values which are not properly scoped
* `schacHomeorganization` attribute if doesn't match against a value from `shibmd:Scope`

## Note
* regexp
* attributemap names

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
            // Default attributes with scope attributes.
            // 'attributesWithScope' => array('eduPersonPrincipalName', 'eduPersonScopedAffiliation'),
            // Default scopeAttribute
            // 'scopeAttributes' => array('schacHomeOrganization'),
       ),
```
