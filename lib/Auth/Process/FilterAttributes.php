<?php

/**
 * Filter to remove attribute values which are not properly scoped.
 *
 * @author Adam Lantos  NIIF / Hungarnet
 * @package SimpleSAMLphp
 */
class sspmod_attributescope_Auth_Process_FilterAttributes extends SimpleSAML_Auth_ProcessingFilter
{
    /**
     * Apply filter.
     *
     * @param array &$request the current request
     */
    public function process(&$request)
    {
        $src = $request['Source'];
        if (!isset($src['scopedattributes']) ||
                !is_array($src['scopedattributes']) ||
                !count($src['scopedattributes'])) {
            return;
        }
        $scopedAttributes = $src['scopedattributes'];

        if (!isset($src['scopes']) ||
                !is_array($src['scopes']) ||
                !count($src['scopes'])) {
            return;
        }
        $scopes = $src['scopes'];

        foreach ($scopedAttributes as $scopedAttribute) {
            if (!isset($request['Attributes'][$scopedAttribute])) {
                continue;
            }
            $values = $request['Attributes'][$scopedAttribute];
            $newValues = array();
            foreach ($values as $value) {
                if ($this->isProperlyScoped($value, $scopes)) {
                    $newValues[] = $value;
                } else {
                    SimpleSAML_Logger::warning("Attribute value $value is not properly scoped");
                }
            }
            $request['Attributes'][$scopedAttribute] = $newValues;
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
