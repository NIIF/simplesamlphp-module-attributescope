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
        if (!isset($src['scope']) ||
                !is_array($src['scope']) ||
                !count($src['scope'])) {
            SimpleSAML_Logger::warning("The IdP does not have scope! entityId: ". $src['entityid']);
            return;
        }
        $scopes = $src['scope'];

        foreach ($this->scopedattributes as $scopedattribute) {
            if (!isset($request['Attributes'][$scopedattribute])) {
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
            $request['Attributes'][$scopedattribute] = $newValues;
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
            $preg = '/^[^@]*@' . preg_quote($scope). '$/';
            if (preg_match($preg, $value) == 1) {
                return true;
            }
        }
    }
}
