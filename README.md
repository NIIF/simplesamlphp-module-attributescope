Attributescope filter module
============================

This module ensures that scoped attributes (such as eduPersonPrincipalName)
have the right scopes defined in the entity metadata.

It removes values 
* that should be scoped (see `attributesWithScope` below) but are not;
* whose scope does not match [https://wiki.shibboleth.net/confluence/display/SC/ShibMetaExt+V1.0](shibmd:Scope) element in the metadata.

Additionally, it is also capable to handle 'scope attributes' such as _schacHomeOrganization_ that should be equivalent to `shibmd:Scope` element in the metadata.

## Notes and limitations
* Regular expressions in `shibmd:Scope` are not supported.
* It is recommended to run this filter after _oid2name_. Please note that attribute names in the module configuration are case sensitive and must match the names in attributemaps.

## Installing the module
You can install the module with composer:

    composer require niif/simplesamlphp-module-attributescope

## Example configuration

_config/config.php_

```
   authproc.sp = array(
       ...
        // 49 => array('class' => 'core:AttributeMap', 'oid2name'),
        // Verify scoped attributes with the metadata:
        50 => array(
            'class' => 'attributescope:FilterAttributes',
            // Default attributes with scope attributes.
            // 'attributesWithScope' => array('eduPersonPrincipalName', 'eduPersonScopedAffiliation'),
            // Default scopeAttribute
            // 'scopeAttributes' => array('schacHomeOrganization'),
       ),
```
