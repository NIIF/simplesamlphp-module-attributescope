<?php

/**
 * Filter to remove
 * * all attributes if there is no `shibmd:Scope` value for the IdP
 * * attribute values which are not properly scoped
 * * configured scopeAttribute if it doesn't match against a value from `shibmd:Scope`.
 *
 * Note:
 * * regexp in scope values are not supported.
 * * Configured attribute names MUST match with names in attributemaps. It is case-sensitive.
 *
 * @author Adam Lantos  NIIF / Hungarnet
 * @author Gyula Szabo  NIIF / Hungarnet
 * @author Tamas Frank  NIIF / Hungarnet
 */
class sspmod_attributescope_Auth_Process_FilterAttributes extends SimpleSAML_Auth_ProcessingFilter
{
    private $attributesWithScope = array(
        'eduPersonPrincipalName',
        'eduPersonScopedAffiliation',
        );

    private $scopeAttributes = array(
        'schacHomeOrganization',
        );

    private $ignoreCheckForEntities = array();

    private $attributesWithScopeSuffix = array();

    private $ignoreCase = false;

    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);
        if (array_key_exists('attributesWithScope', $config)) {
            $this->attributesWithScope = $config['attributesWithScope'];
        }
        if (array_key_exists('scopeAttributes', $config)) {
            $this->scopeAttributes = $config['scopeAttributes'];
        }
        if (array_key_exists('ignoreCheckForEntities', $config)) {
            $this->ignoreCheckForEntities = $config['ignoreCheckForEntities'];
        }
        if (array_key_exists('attributesWithScopeSuffix', $config)) {
            $this->attributesWithScopeSuffix = $config['attributesWithScopeSuffix'];
        }
        if (array_key_exists('ignoreCase', $config)) {
            $this->ignoreCase = $config['ignoreCase'];
        }
    }

    /**
     * Apply filter.
     *
     * @param array &$request the current request
     */
    public function process(&$request)
    {
        $src = $request['Source'];

        if (isset($src['entityid']) && in_array($src['entityid'], $this->ignoreCheckForEntities, true)) {
            SimpleSAML_Logger::debug('Ignoring scope checking for assertions from ' . $src['entityid']);
            return;
        }

        $noscope = false;
        if (!isset($src['scope']) ||
                !is_array($src['scope']) ||
                !count($src['scope'])) {
            SimpleSAML_Logger::warning('No scope extension in IdP metadata, all scoped attributes are filtered out!');
            $noscope = true;
        }
        $scopes = $noscope ? array() : $src['scope'];

        foreach ($this->attributesWithScope as $attributesWithScope) {
            if (!isset($request['Attributes'][$attributesWithScope])) {
                continue;
            }
            if ($noscope) {
                SimpleSAML_Logger::info('Attribute '.$attributesWithScope.' is filtered out due to missing scope information in IdP metadata.');
                unset($request['Attributes'][$attributesWithScope]);
                continue;
            }
            $values = $request['Attributes'][$attributesWithScope];
            $newValues = array();
            foreach ($values as $value) {
                if ($this->isProperlyScoped($value, $scopes)) {
                    $newValues[] = $value;
                } else {
                    SimpleSAML_Logger::warning('Attribute value ('.$value.') is removed by attributescope check.');
                }
            }

            if (count($newValues)) {
                $request['Attributes'][$attributesWithScope] = $newValues;
            } else {
                unset($request['Attributes'][$attributesWithScope]);
            }
        }
        // Filter out scopeAttributes if the value does not match any scope values
        foreach ($this->scopeAttributes as $scopeAttribute) {
            if (array_key_exists($scopeAttribute, $request['Attributes'])) {
                if (count($request['Attributes'][$scopeAttribute]) != 1) {
                    SimpleSAML_Logger::warning('$scopeAttribute (' . $scopeAttribute . ') must be single valued. Filtering out.');
                    unset($request['Attributes'][$scopeAttribute]);
                } elseif (!in_array($request['Attributes'][$scopeAttribute][0], $scopes)) {
                    SimpleSAML_Logger::warning('Scope attribute (' . $scopeAttribute . ') does not match metadata. Filtering out.');
                    unset($request['Attributes'][$scopeAttribute]);
                }
            }
        }

        foreach ($this->attributesWithScopeSuffix as $attributeWithSuffix) {
            if (!isset($request['Attributes'][$attributeWithSuffix])) {
                continue;
            }
            if ($noscope) {
                SimpleSAML_Logger::info('Attribute '.$attributeWithSuffix.' is filtered out due to missing scope information in IdP metadata.');
                unset($request['Attributes'][$attributeWithSuffix]);
                continue;
            }
            $values = $request['Attributes'][$attributeWithSuffix];
            $newValues = array();
            foreach ($values as $value) {
                if ($this->isProperlySuffixed($value, $scopes)) {
                    $newValues[] = $value;
                } else {
                    SimpleSAML_Logger::warning('Attribute value ('.$value.') is removed by attributeWithScopeSuffix check.');
                }
            }

            if (count($newValues)) {
                $request['Attributes'][$attributeWithSuffix] = $newValues;
            } else {
                unset($request['Attributes'][$attributeWithSuffix]);
            }
        }
    }

    /**
     * Determines whether an attribute value is properly scoped.
     *
     * @param string $value The attribute value to check
     * @param array  $scopes The array of scopes for the Idp
     *
     * @return bool true if properly scoped
     */
    private function isProperlyScoped($value, $scopes)
    {
        foreach ($scopes as $scope) {
            $preg = '/^[^@]+@'.preg_quote($scope).'$/' . ($this->ignoreCase ? 'i' : '');
            if (preg_match($preg, $value) == 1) {
                return true;
            }
        }
    }

    /**
     * Determines whether an attribute value is properly suffixed with the scope.
     * @ and (literal) . are used for suffix boundries
     *
     * @param string $value The attribute value to check
     * @param array  $scopes The array of scopes for the IdP
     *
     * @return bool true if attribute is suffixed with a scope
     */
    private function isProperlySuffixed($value, $scopes)
    {
        foreach ($scopes as $scope) {
            $scopeRegex = '/^[^@]+@(.*\.)?'.preg_quote($scope).'$/' . ($this->ignoreCase ? 'i' : '');
            $subdomainRegex = '/^([^@]*\.)?'.preg_quote($scope).'$/' . ($this->ignoreCase ? 'i' : '');
            if (preg_match($subdomainRegex, $value) === 1 || preg_match($scopeRegex, $value) === 1) {
                return true;
            }
        }
    }
}
