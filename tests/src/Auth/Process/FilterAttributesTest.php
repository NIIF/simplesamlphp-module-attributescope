<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\attributescope\Auth\Process;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\attributescope\Auth\Process\FilterAttributes;

class FilterAttributesTest extends TestCase
{
    /**
     * Helper function to run the filter with a given configuration.
     *
     * @param  array $config The filter configuration.
     * @param  array $request The request state.
     * @return array  The state array after processing.
     */
    private static function processFilter(array $config, array $request)
    {
        $filter = new FilterAttributes($config, null);
        $filter->process($request);
        return $request;
    }

    protected function setUp(): void
    {
        Configuration::loadFromArray([], '[ARRAY]', 'simplesaml');
    }

    /**
     * Test scoped attributes don't match scope
     * @param array $source The IDP source info
     * @dataProvider wrongScopeDataProvider
     */
    public function testWrongScope($source)
    {
        $config = [
            'attributesWithScopeSuffix' => ['sampleSuffixedAttribute']
        ];
        $request = [
            'Attributes' => [
                'eduPersonPrincipalName' => ['joe@example.com'],
                'nonScopedAttribute' => ['not-removed'],
                'eduPersonScopedAffiliation' => ['student@example.com', 'staff@example.com', 'missing-scope'],
                'schacHomeOrganization' => ['example.com'],
                'sampleSuffixedAttribute' => ['joe@example.com'],
            ],
            'Source' => $source,
        ];
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $expectedData = ['nonScopedAttribute' => ['not-removed']];
        $this->assertEquals($expectedData, $attributes, "Only incorrectly scoped attributes should be removed");
    }

    /**
     * Provide data for the tests
     * @return array test cases with each subtest being array of arguments
     */
    public static function wrongScopeDataProvider()
    {
        return [
            // Empty Source
            [[]],
            // No scope value set
            [['scope' => null]],
            // Empty array
            [['scope' => []]],
            // Scope mismatch on leading .
            [['scope' => ['.example.com']]],
            // Scope mismatch on 's' instead of '.
            [['scope' => ['examplescom']]],
            // No wildcard match
            [['scope' => ['.com']]],
        ];
    }

    /**
     * Test correct scope
     * @param array $source The IDP source info
     * @dataProvider correctScopeDataProvider
     */
    public function testCorrectScope($source)
    {
        $expectedData = [
            'eduPersonPrincipalName' => ['joe@example.com'],
            'nonScopedAttribute' => ['not-removed'],
            'eduPersonScopedAffiliation' => ['student@example.com', 'staff@example.com'],
            'schacHomeOrganization' => ['example.com']
        ];
        $config = [];
        $request = [
            'Attributes' => $expectedData,
            'Source' => $source,
        ];
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($expectedData, $attributes, "All attributes should survive");
    }

    /**
     * Provide data for the tests
     * @return array test cases with each subtest being array of arguments
     */
    public static function correctScopeDataProvider()
    {
        return [
            // Correct scope
            [['scope' => ['example.com']]],
            // Multiple scopes
            [['scope' => ['abc.com', 'example.com', 'xyz.com']]],
        ];
    }

    public function testIgnoreCaseInScope()
    {
        $config = [
            'attributesWithScopeSuffix' => ['sampleSuffixedAttribute'],
            'ignoreCase' => true,
        ];
        $request = [
            'Attributes' => [
                'eduPersonScopedAffiliation' => ['student@example.com', 'staff@EXAMPLE.COM', 'member@bad.com'],
                'sampleSuffixedAttribute' => ['joe@example.com', 'bob@EXAMPLE.COM', 'wrong@bad.com'],
            ],
            'Source' => [
                'scope' => ['example.com']
            ]
        ];
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $expectedData = [
            'eduPersonScopedAffiliation' => ['student@example.com', 'staff@EXAMPLE.COM'],
            'sampleSuffixedAttribute' => ['joe@example.com', 'bob@EXAMPLE.COM'],
            ];
        $this->assertEquals($expectedData, $attributes, "Scope case is ignored.");
    }

    /**
     * Test correct scope when multi-valued attribute has some conforming and some non-conforming values
     */
    public function testMixedMultivaluedAttributes()
    {
        $config = [];
        $request = [
            'Attributes' => [
                'nonScopedAttribute' => ['not-removed'],
                'eduPersonScopedAffiliation' => [
                    'faculty@abc.com',
                    'student@example.com',
                    'member@EXamPLE.com', // scope is case sensitive
                    'staff@other.com',
                    'member@a@example.com',
                    '@example.com'
                ],
                // schacHomeOrganization is required to be single valued and gets filtered out if multi-valued
                'schacHomeOrganization' => ['abc.com', 'example.com', 'other.com']
            ],
            'Source' => [
                'scope' => ['example.com'],
                'entityid' => 'https://example.com/idp'
            ],
        ];
        $result = self::processFilter($config, $request);
        $expectedData = [
            'nonScopedAttribute' => ['not-removed'],
            'eduPersonScopedAffiliation' => ['student@example.com'],
        ];
        $attributes = $result['Attributes'];
        $this->assertEquals($expectedData, $attributes, "Incorrectly scoped values should be removed");
    }

    /**
     * Test disabling scope check for specific entityIds
     */
    public function testIgnoreSourceScope()
    {

        $expectedData = [
            'nonScopedAttribute' => ['not-removed'],
            'eduPersonScopedAffiliation' => ['faculty@abc.com', 'student@example.com', 'staff@other.com'],
            'schacHomeOrganization' => ['random.com']
        ];
        $request = [
            'Attributes' => $expectedData,
            'Source' => [
                'scope' => ['example.com'],
                'entityid' => 'https://example.com/idp'
            ]
        ];

        // Test with entity ID that does NOT match the Source
        $config = [
            'ignoreCheckForEntities' => ['https://NOMATCH.com/idp']
        ];
        $result = self::processFilter($config, $request);

        $attributes = $result['Attributes'];
        $this->assertFalse(array_key_exists('schacHomeOrganization', $attributes), 'Scope check shouldn\t be ignored');

        // Test with entity ID that does match the Source
        $config = [
            'ignoreCheckForEntities' => ['https://example.com/idp']
        ];
        $result = self::processFilter($config, $request);

        $attributes = $result['Attributes'];
        $this->assertEquals($expectedData, $attributes, "Scope check ignored");
    }

    /**
     * Test attributes values that need to end with the scope or some subdomain of the scope.
     */
    public function testAttributeSuffix()
    {

        $request = [
            'Attributes' => [
                'department' => [
                    // Valid values
                    'engineering.example.com', // Subdomain
                    'example.com', // scope
                    '.example.com',
                    // Invalid values
                    'invalid-example.com', // not subdomain
                    'cexample.com',
                    'examplecom',
                ],
                'email' => [
                    // Valid values
                    'user@example.com',
                    'user@gsb.example.com',
                    // Invalid values
                    'user@invalid-example.com',
                    'user@examplecom',
                    'user@cexample.com',
                    'abc@efg@example.com', // double '@'
                    // scoped values need data before the '@'
                    '@example.com',
                    '@other.example.com',
                    ],
            ],
            'Source' => [
                'scope' => ['example.com'],
                'entityid' => 'https://example.com/idp'
            ]
        ];

        $config = [
            'attributesWithScopeSuffix' => ['department', 'email']
        ];
        $result = self::processFilter($config, $request);

        $attributes = $result['Attributes'];
        $expectedData = [
            'department' => [
                'engineering.example.com',
                'example.com',
                '.example.com',
            ],
            'email' => [
                'user@example.com',
                'user@gsb.example.com',
            ],
        ];
        $this->assertEquals($expectedData, $attributes, "Incorrectly suffixed variables should be removed");
    }
}
