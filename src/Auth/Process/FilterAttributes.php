<?php

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

declare(strict_types=1);

namespace SimpleSAML\Module\attributescope\Auth\Process;

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
    private array $attributesWithScope = [
        'eduPersonPrincipalName',
        'eduPersonScopedAffiliation',
    ];

    private array $scopeAttributes = [
        'schacHomeOrganization',
    ];

    private array $ignoreCheckForEntities = [];

    private array $attributesWithScopeSuffix = [];

    private bool $ignoreCase = false;

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
     * Process the filter
     *
     * @param array $state the state array
     *
     * @return void
     */
    public function process(array &$state): void
    {
        $src = $state['Source'];

        if (isset($src['entityid']) && in_array($src['entityid'], $this->ignoreCheckForEntities, true)) {
            Logger::debug('Ignoring scope checking for assertions from ' . $src['entityid']);
            return;
        }

        $noscope = false;
        if (
            !isset($src['scope'])
            || !is_array($src['scope'])
            || !count($src['scope'])
        ) {
            Logger::warning('No scope extension in IdP metadata, all scoped attributes are filtered out!');
            $noscope = true;
        }
        $scopes = $noscope ? [] : $src['scope'];

        foreach ($this->attributesWithScope as $attributesWithScope) {
            if (!isset($state['Attributes'][$attributesWithScope])) {
                continue;
            }
            if ($noscope) {
                Logger::info(
                    'Attribute ' . $attributesWithScope .
                    ' is filtered out due to missing scope information in IdP metadata.'
                );
                unset($state['Attributes'][$attributesWithScope]);
                continue;
            }
            $values = $state['Attributes'][$attributesWithScope];
            $newValues = [];
            foreach ($values as $value) {
                if ($this->isProperlyScoped($value, $scopes)) {
                    $newValues[] = $value;
                } else {
                    Logger::warning('Attribute value (' . $value . ') is removed by attributescope check.');
                }
            }

            if (count($newValues)) {
                $state['Attributes'][$attributesWithScope] = $newValues;
            } else {
                unset($state['Attributes'][$attributesWithScope]);
            }
        }
        // Filter out scopeAttributes if the value does not match any scope values
        foreach ($this->scopeAttributes as $scopeAttribute) {
            if (array_key_exists($scopeAttribute, $state['Attributes'])) {
                if (count($state['Attributes'][$scopeAttribute]) != 1) {
                    Logger::warning(
                        '$scopeAttribute (' . $scopeAttribute . ') must be single valued. Filtering out.'
                    );
                    unset($state['Attributes'][$scopeAttribute]);
                } elseif (!in_array($state['Attributes'][$scopeAttribute][0], $scopes)) {
                    Logger::warning(
                        'Scope attribute (' . $scopeAttribute . ') does not match metadata. Filtering out.'
                    );
                    unset($state['Attributes'][$scopeAttribute]);
                }
            }
        }

        foreach ($this->attributesWithScopeSuffix as $attributeWithSuffix) {
            if (!isset($state['Attributes'][$attributeWithSuffix])) {
                continue;
            }
            if ($noscope) {
                Logger::info(
                    'Attribute ' . $attributeWithSuffix .
                    ' is filtered out due to missing scope information in IdP metadata.'
                );
                unset($state['Attributes'][$attributeWithSuffix]);
                continue;
            }
            $values = $state['Attributes'][$attributeWithSuffix];
            $newValues = [];
            foreach ($values as $value) {
                if ($this->isProperlySuffixed($value, $scopes)) {
                    $newValues[] = $value;
                } else {
                    Logger::warning('Attribute value (' . $value . ') is removed by attributeWithScopeSuffix check.');
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
    private function isProperlyScoped(string $value, array $scopes): bool
    {
        foreach ($scopes as $scope) {
            $preg = '/^[^@]+@' . preg_quote($scope) . '$/' . ($this->ignoreCase ? 'i' : '');
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
    private function isProperlySuffixed(string $value, array $scopes): bool
    {
        foreach ($scopes as $scope) {
            $scopeRegex = '/^[^@]+@(.*\.)?' . preg_quote($scope) . '$/' . ($this->ignoreCase ? 'i' : '');
            $subdomainRegex = '/^([^@]*\.)?' . preg_quote($scope) . '$/' . ($this->ignoreCase ? 'i' : '');
            if (preg_match($subdomainRegex, $value) === 1 || preg_match($scopeRegex, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
