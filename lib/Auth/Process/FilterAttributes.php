<?php

/**
 * Filter to remove attribute values which are not properly scoped.
 *
 * @author Adam Lantos  NIIF / Hungarnet
 * @package SimpleSAMLphp
 */
class sspmod_attributescope_Auth_Process_FilterAttributes extends SimpleSAML_Auth_ProcessingFilter
{

    private $scopedattributes = array(
        'eduPersonPrincipalName',
        'eduPersonScopedAffiliation'
        );

    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);
        if (array_key_exists('scopedattributes', $config)) {
            $this->scopedattributes = $config['scopedattributes'];
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
        $noscope = false;
        if (!isset($src['scope']) ||
                !is_array($src['scope']) ||
                !count($src['scope'])) {
            SimpleSAML_Logger::warning("The IdP does not have scope, filter out all scoped attributes! entityId: ". $src['entityid']);
            $noscope = true;
        }
        $scopes = $src['scope'];

        foreach ($this->scopedattributes as $scopedattribute) {
            if (!isset($request['Attributes'][$scopedattribute])) {
                continue;
            }
            if ($noscope) {
                SimpleSAML_Logger::info("The IdP does not have scope! Attribute ". $scopedattribute ." filtered out. ");
                unset($request['Attributes'][$scopedattribute]);
                continue;
            }
            $values = $request['Attributes'][$scopedattribute];
            $newValues = array();
            foreach ($values as $value) {
                if ($this->isProperlyScoped($value, $scopes)) {
                    $newValues[] = $value;
                } else {
                    SimpleSAML_Logger::warning("Attribute value $value is not properly scoped");
                }
            }

            if (count($newValues)) {
                $request['Attributes'][$scopedattribute] = $newValues;
            } else {
                unset($request['Attributes'][$scopedattribute]);
            }
        }
        // Filter out schacHomeOrganization if the value not match to any scope value
        if (array_key_exists('schacHomeOrganization', $request['Attributes'])) {
            if (count($request['Attributes']['schacHomeOrganization']) != 1) {
                    SimpleSAML_Logger::warning("Too much schacHomeOrganization attribute element. Only one allowed. Filtered out.");
                    unset($request['Attributes']['schacHomeOrganization']);
            } elseif (! in_array($request['Attributes']['schacHomeOrganization'][0], $scopes)) {
                SimpleSAML_Logger::warning("There is no schacHomeOrganization in scopes array. Filtered out.");
                unset($request['Attributes']['schacHomeOrganization']);
            }
        }
    }

    /**
     * Determines whether an attribute value is properly scoped.
     *
     * @param string $value
     * @param array $scopes
     * @return bool
     */
    private function isProperlyScoped($value, $scopes)
    {
        foreach ($scopes as $scope) {
            if ($this->isScopeRegexp($scope)) {
                $preg = $scope;
            } else {
                $preg = '/^[^@]*@' . preg_quote($scope). '$/';
            }
            if (preg_match($preg, $value) == 1) {
                return true;
            }
        }
    }

    /**
     * Is scope regexp or not. We cant get it from 'Source'
     * @param  string  $scopetext the value of scope
     * @return boolean
     */
    private function isScopeRegexp($scopetext)
    {
        // Check regexp as SimpleSAML_Metadata_SAMLBuilder do
        if (1 === preg_match('/[\$\^\)\(\*\|\\\\]/', $scopetext)) {
            return true;
        }
        return false;
    }
}
