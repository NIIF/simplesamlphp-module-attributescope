<?php

class Test_sspmod_attributescope_Auth_Process_FilterAttributes extends PHPUnit_Framework_TestCase
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
        $filter = new sspmod_attributescope_Auth_Process_FilterAttributes($config, null);
        $filter->process($request);
        return $request;
    }

    /**
     * Test scoped attributes don't match scope
     * @param array $source The IDP source info
     * @dataProvider testWrongScopeDataProvider
     */
    public function testWrongScope($source)
    {
        $config = array(
            'attributesWithScopeSuffix' => array('sampleSuffixedAttribute')
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('joe@example.com'),
                'nonScopedAttribute' => array('not-removed'),
                'eduPersonScopedAffiliation' => array('student@example.com', 'staff@example.com', 'missing-scope'),
                'schacHomeOrganization' => array('example.com'),
                'sampleSuffixedAttribute' => array('joe@example.com'),
            ),
            'Source' => $source,
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $expectedData = array('nonScopedAttribute' => array('not-removed'));
        $this->assertEquals($expectedData, $attributes, "Only incorrectly scoped attributes should be removed");
    }

    /**
     * Provide data for the tests
     * @return array test cases with each subtest being array of arguments
     */
    public function testWrongScopeDataProvider()
    {
        return array(
            // Empty Source
            array(array()),
            // No scope value set
            array(array('scope' => null)),
            // Empty array
            array(array('scope' => array())),
            // Scope mismatch on leading .
            array(array('scope' => array('.example.com'))),
            // Scope mismatch on 's' instead of '.
            array(array('scope' => array('examplescom'))),
            // No wildcard match
            array(array('scope' => array('.com'))),
        );
    }

    /**
     * Test correct scope
     * @param array $source The IDP source info
     * @dataProvider testCorrectScopeDataProvider
     */
    public function testCorrectScope($source)
    {
        $expectedData = array(
            'eduPersonPrincipalName' => array('joe@example.com'),
            'nonScopedAttribute' => array('not-removed'),
            'eduPersonScopedAffiliation' => array('student@example.com', 'staff@example.com'),
            'schacHomeOrganization' => array('example.com')
        );
        $config = array();
        $request = array(
            'Attributes' => $expectedData,
            'Source' => $source,
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($expectedData, $attributes, "All attributes should survive");
    }

    /**
     * Provide data for the tests
     * @return array test cases with each subtest being array of arguments
     */
    public function testCorrectScopeDataProvider()
    {
        return array(
            // Correct scope
            array(array('scope' => array('example.com'))),
            // Multiple scopes
            array(array('scope' => array('abc.com', 'example.com', 'xyz.com'))),
        );
    }

    /**
     * Test correct scope when multi-valued attribute has some conforming and some non-conforming values
     */
    public function testMixedMultivaluedAttributes()
    {
        $config = array();
        $request = array(
            'Attributes' => array(
                'nonScopedAttribute' => array('not-removed'),
                'eduPersonScopedAffiliation' => array(
                    'faculty@abc.com',
                    'student@example.com',
                    'staff@other.com',
                    'member@a@example.com',
                    '@example.com'
                ),
                // schacHomeOrganization is required to be single valued and gets filtered out if multi-valued
                'schacHomeOrganization' => array('abc.com', 'example.com', 'other.com')
            ),
            'Source' => array(
                'scope' => array('example.com'),
                'entityid' => 'https://example.com/idp'
            ),
        );
        $result = self::processFilter($config, $request);
        $expectedData = array(
            'nonScopedAttribute' => array('not-removed'),
            'eduPersonScopedAffiliation' => array('student@example.com'),
        );
        $attributes = $result['Attributes'];
        $this->assertEquals($expectedData, $attributes, "Incorrectly scoped values should be removed");
    }

    /**
     * Test disabling scope check for specific entityIds
     */
    public function testIgnoreSourceScope()
    {

        $expectedData = array(
            'nonScopedAttribute' => array('not-removed'),
            'eduPersonScopedAffiliation' => array('faculty@abc.com', 'student@example.com', 'staff@other.com'),
            'schacHomeOrganization' => array('random.com')
        );
        $request = array(
            'Attributes' => $expectedData,
            'Source' => array(
                'scope' => array('example.com'),
                'entityid' => 'https://example.com/idp'
            )
        );

        // Test with entity ID that does NOT match the Source
        $config = array(
            'ignoreCheckForEntities' => array('https://NOMATCH.com/idp')
        );
        $result = self::processFilter($config, $request);

        $attributes = $result['Attributes'];
        $this->assertFalse(array_key_exists('schacHomeOrganization', $attributes), 'Scope check shouldn\t be ignored');

        // Test with entity ID that does match the Source
        $config = array(
            'ignoreCheckForEntities' => array('https://example.com/idp')
        );
        $result = self::processFilter($config, $request);

        $attributes = $result['Attributes'];
        $this->assertEquals($expectedData, $attributes, "Scope check ignored");
    }

    /**
     * Test attributes values that need to end with the scope or some subdomain of the scope.
     */
    public function testAttributeSuffix()
    {

        $request = array(
            'Attributes' => array(
                'department' => array(
                    // Valid values
                    'engineering.example.com', // Subdomain
                    'example.com', // scope
                    '.example.com',
                    // Invalid values
                    'invalid-example.com', // not subdomain
                    'cexample.com',
                    'examplecom',
                ),
                'email' => array(
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
                    ),
            ),
            'Source' => array(
                'scope' => array('example.com'),
                'entityid' => 'https://example.com/idp'
            )
        );

        $config = array(
            'attributesWithScopeSuffix' => array('department', 'email')
        );
        $result = self::processFilter($config, $request);

        $attributes = $result['Attributes'];
        $expectedData = array(
            'department' => array(
                'engineering.example.com',
                'example.com',
                '.example.com',
            ),
            'email' => array(
                'user@example.com',
                'user@gsb.example.com',
            ),
        );
        $this->assertEquals($expectedData, $attributes, "Incorrectly suffixed variables should be removed");
    }
}
