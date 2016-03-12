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
        $config = array();
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('joe@example.com'),
                'nonScopedAttribute' => array('not-removed'),
                'eduPersonScopedAffiliation' => array('student@example.com', 'staff@example.com'),
                'schacHomeOrganization' => array('example.com')
            ),
            'Source' => $source,
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $expectedData = array('nonScopedAttribute' => array('not-removed'));
        $this->assertEquals($expectedData, $attributes, "Only none-scoped attributes should be removed");
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
        $this->assertEquals($expectedData, $attributes, "All attributes survived");
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
     * Test correct scope when multi-valued attribute has some conforming and some none-conforming values
     */
    public function testMixedMultivaluedAttributes()
    {
        $config = array();
        $request = array(
            'Attributes' => array(
                'nonScopedAttribute' => array('not-removed'),
                'eduPersonScopedAffiliation' => array('faculty@abc.con', 'student@example.com', 'staff@other.com'),
                // schacHomeOrganization is required to be single valued and gets filtered out if multi-valued
                'schacHomeOrganization' => array('abc.com', 'example.com', 'other.com')
            ),
            'Source' => array('scope' => array('example.com')),
        );
        $result = self::processFilter($config, $request);
        $expectedData = array(
            'nonScopedAttribute' => array('not-removed'),
            'eduPersonScopedAffiliation' => array('student@example.com'),
//            'schacHomeOrganization' => array('example.com')
        );
        $attributes = $result['Attributes'];
        $this->assertEquals($expectedData, $attributes, "All attributes survived");
    }
}
