<?php
declare(strict_types=1);

/**
 * Filter to remove
 * * all attributes if there is no `shibmd:Scope` value for the IdP
 * * attribute values which are not properly scoped
 * * configured scopeAttribute if it doesn't match against
 *   a value from `shibmd:Scope`.
 *
 * Note:
 * * regexp in scope values are not supported.
 * * Configured attribute names MUST match with names in attributemaps.
 *   It is case-sensitive.
 *
 * @category SimpleSAML
 * @package  SimpleSAML\Module\niif
 * @author   Adam Lantos <adam.lantos@niif.hu>
 * @author   Gyula Szabo <gyufi@niif.hu>
 * @author   Gyula Szabo <szabo.gyula@sztaki.hu>
 * @author   Tamas Frank <sitya@niif.hu>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License
 * @link     https://github.com/niif/simplesamlphp-module-attributescope
 */

namespace SimpleSAML\Module\niif\attributescope\Auth\Process;
use SimpleSAML\Auth;
use SimpleSAML\Logger;

/**
 * Filter to remove
 * * all attributes if there is no `shibmd:Scope` value for the IdP
 * * attribute values which are not properly scoped
 * * configured scopeAttribute if it doesn't match against
 *   a value from `shibmd:Scope`.
 *
 * Note:
 * * regexp in scope values are not supported.
 * * Configured attribute names MUST match with names in attributemaps.
 *   It is case-sensitive.
 *
 * @category SimpleSAML
 * @package  SimpleSAML\Module\niif
 * @author   Adam Lantos <adam.lantos@niif.hu>
 * @author   Gyula Szabo <gyufi@niif.hu>
 * @author   Gyula Szabo <szabo.gyula@sztaki.hu>
 * @author   Tamas Frank <sitya@niif.hu>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License
 * @link     https://github.com/niif/simplesamlphp-module-attributescope
 */



class FilterAttributes extends Auth\ProcessingFilter
{
    private array $_attributesWithScope = array(
        'eduPersonPrincipalName',
        'eduPersonScopedAffiliation',
        );

    private array $_scopeAttributes = array(
        'schacHomeOrganization',
        );

    private array $_ignoreCheckForEntities = array();

    private array $_attributesWithScopeSuffix = array();

    private bool $_ignoreCase = false;

    /**
     * Constructor
     * 
     * @param array $config   simplesamlphp configuration
     * @param mixed $reserved reserved
     */
    public function __construct(array $config, $reserved)
    {
        parent::__construct($config, $reserved);
        if (array_key_exists('attributesWithScope', $config)) {
            $this->_attributesWithScope = $config['attributesWithScope'];
        }
        if (array_key_exists('scopeAttributes', $config)) {
            $this->_scopeAttributes = $config['scopeAttributes'];
        }
        if (array_key_exists('ignoreCheckForEntities', $config)) {
            $this->_ignoreCheckForEntities = $config['ignoreCheckForEntities'];
        }
        if (array_key_exists('attributesWithScopeSuffix', $config)) {
            $this->_attributesWithScopeSuffix = $config['attributesWithScopeSuffix'];
        }
        if (array_key_exists('ignoreCase', $config)) {
            $this->_ignoreCase = $config['ignoreCase'];
        }
    }

    /**
     * Process the filter
     * 
     * @param array $state the state array
     * 
     * @return void
     */
    public function process(array &$state): void
    {
        $src = $state['Source'];

        if (isset($src['entityid']) && in_array($src['entityid'], $this->_ignoreCheckForEntities, true)) {
            Logger::debug('Ignoring scope checking for assertions from ' . $src['entityid']);
            return;
        }

        $noscope = false;
        if (!isset($src['scope']) 
            || !is_array($src['scope']) 
            || !count($src['scope'])
        ) {
            Logger::warning('No scope extension in IdP metadata, all scoped attributes are filtered out!');
            $noscope = true;
        }
        $scopes = $noscope ? array() : $src['scope'];

        foreach ($this->_attributesWithScope as $attributesWithScope) {
            if (!isset($state['Attributes'][$attributesWithScope])) {
                continue;
            }
            if ($noscope) {
                Logger::info('Attribute '.$attributesWithScope.' is filtered out due to missing scope information in IdP metadata.');
                unset($state['Attributes'][$attributesWithScope]);
                continue;
            }
            $values = $state['Attributes'][$attributesWithScope];
            $newValues = array();
            foreach ($values as $value) {
                if ($this->_isProperlyScoped($value, $scopes)) {
                    $newValues[] = $value;
                } else {
                    Logger::warning('Attribute value ('.$value.') is removed by attributescope check.');
                }
            }

            if (count($newValues)) {
                $state['Attributes'][$attributesWithScope] = $newValues;
            } else {
                unset($state['Attributes'][$attributesWithScope]);
            }
        }
        // Filter out scopeAttributes if the value does not match any scope values
        foreach ($this->_scopeAttributes as $scopeAttribute) {
            if (array_key_exists($scopeAttribute, $state['Attributes'])) {
                if (count($state['Attributes'][$scopeAttribute]) != 1) {
                    Logger::warning('$scopeAttribute (' . $scopeAttribute . ') must be single valued. Filtering out.');
                    unset($state['Attributes'][$scopeAttribute]);
                } elseif (!in_array($state['Attributes'][$scopeAttribute][0], $scopes)) {
                    Logger::warning('Scope attribute (' . $scopeAttribute . ') does not match metadata. Filtering out.');
                    unset($state['Attributes'][$scopeAttribute]);
                }
            }
        }

        foreach ($this->_attributesWithScopeSuffix as $attributeWithSuffix) {
            if (!isset($state['Attributes'][$attributeWithSuffix])) {
                continue;
            }
            if ($noscope) {
                Logger::info('Attribute '.$attributeWithSuffix.' is filtered out due to missing scope information in IdP metadata.');
                unset($state['Attributes'][$attributeWithSuffix]);
                continue;
            }
            $values = $state['Attributes'][$attributeWithSuffix];
            $newValues = array();
            foreach ($values as $value) {
                if ($this->_isProperlySuffixed($value, $scopes)) {
                    $newValues[] = $value;
                } else {
                    Logger::warning('Attribute value ('.$value.') is removed by attributeWithScopeSuffix check.');
                }
            }

            if (count($newValues)) {
                $state['Attributes'][$attributeWithSuffix] = $newValues;
            } else {
                unset($state['Attributes'][$attributeWithSuffix]);
            }
        }
    }

    /**
     * Determines whether an attribute value is properly scoped.
     *
     * @param string $value  The attribute value to check
     * @param array  $scopes The array of scopes for the Idp
     *
     * @return bool true if properly scoped
     */
    private function _isProperlyScoped(string $value, array $scopes): bool
    {
        foreach ($scopes as $scope) {
            $preg = '/^[^@]+@'.preg_quote($scope).'$/' . ($this->_ignoreCase ? 'i' : '');
            if (preg_match($preg, $value) == 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines whether an attribute value is properly suffixed with the scope.
     * @ and (literal) . are used for suffix boundries
     *
     * @param string $value  The attribute value to check
     * @param array  $scopes The array of scopes for the IdP
     *
     * @return bool true if attribute is suffixed with a scope
     */
    private function _isProperlySuffixed(string $value, array $scopes): bool
    {
        foreach ($scopes as $scope) {
            $scopeRegex = '/^[^@]+@(.*\.)?'.preg_quote($scope).'$/' . ($this->_ignoreCase ? 'i' : '');
            $subdomainRegex = '/^([^@]*\.)?'.preg_quote($scope).'$/' . ($this->_ignoreCase ? 'i' : '');
            if (preg_match($subdomainRegex, $value) === 1 || preg_match($scopeRegex, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
