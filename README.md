Attributescope filter module
============================

This module ensures that scoped attributes (such as eduPersonPrincipalName)
have the right scopes defined in the entity metadata.

It removes values 
* that should be scoped (see `attributesWithScope` below) but are not;
* whose scope does not match [shibmd:Scope](https://wiki.shibboleth.net/confluence/display/SC/ShibMetaExt+V1.0) element in the metadata.

Additionally, it is also capable to handle 'scope attributes' such as _schacHomeOrganization_ that should be equivalent to `shibmd:Scope` element in the metadata.

## Notes and limitations
* Regular expressions in `shibmd:Scope` are not supported.
* It is recommended to run this filter after _oid2name_. Please note that attribute names in the module configuration are case sensitive and must match the names in attributemaps.
* 'scope Attributes' must be singled valued, otherwise they are removed.
* Specifying an attribute in multiple configuration options is likely a user configuration issue. A value will only
  pass if it conforms to the validation rule for each configured option.

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

## Configurations Options

* `attributesWithScope` an array of attributes that should be scoped and should match the scope from the metadata
* `attributesWithScopeSuffix` an array of attributes that have the scope as a suffix. For example, `user@department.example.com` 
and `department.example.com` are both suffixed with `example.com`. Useful when an SP is reliant on `mail` attribute to identify users and
the IdP users various subdomains for mail.
* `scopeAttributes` an array of attributes that should exactly match the scope from the metadata
* `ignoreCheckForEntities` an array of IdP entity IDs to skip scope checking for. Useful when an IdP is a SAML proxy and is trusted to assert any scope.

