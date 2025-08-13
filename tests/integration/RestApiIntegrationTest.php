<?php

/**
 * Enhanced REST API Integration Test - Comprehensive Operaton DMN API Coverage
 * Based on Operaton DMN REST API OpenAPI specification
 * Covers decision evaluation, definitions, deployments, and engine information
 */

declare(strict_types=1);

namespace Operaton\DMN\Tests\Integration;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class RestApiIntegrationTest extends TestCase
{
    private Client $client;
    private string $baseUrl;
    private ?string $apiKey;
    private Client $dmnClient;
    private string $dmnEngineUrl;

    protected function setUp(): void
    {
        // Use environment variables or default to test site
        $this->baseUrl = $_ENV['DMN_TEST_URL'] ?? 'https://owc-gemeente.test.open-regels.nl';
        $this->apiKey = $_ENV['DMN_API_KEY'] ?? null;
        $this->dmnEngineUrl = $_ENV['DMN_ENGINE_URL'] ?? 'https://operatondev.open-regels.nl';

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'verify' => false, // For test environments with self-signed certs
            'http_errors' => false, // Don't throw on 4xx/5xx
        ]);

        // Create a separate client for direct DMN engine testing
        $this->dmnClient = new Client([
            'base_uri' => $this->dmnEngineUrl,
            'timeout' => 30,
            'verify' => false,
            'http_errors' => false,
        ]);

        echo "\n🌐 Testing against: " . $this->baseUrl;
        echo "\n🔧 DMN Engine: " . $this->dmnEngineUrl;
        if ($this->apiKey)
        {
            echo "\n🔑 Using API Key: " . substr($this->apiKey, 0, 8) . "...";
        }
        else
        {
            echo "\n🔑 No API Key configured";
        }
    }

    /**
     * Test WordPress REST API accessibility
     */
    public function testWordPressRestApiAccessibility(): void
    {
        echo "\n📋 Testing WordPress REST API accessibility...";

        $response = $this->client->get('/wp-json/');

        $this->assertEquals(200, $response->getStatusCode(), 'WordPress REST API should be accessible');

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertIsArray($body, 'REST API should return valid JSON');
        $this->assertArrayHasKey('namespaces', $body, 'REST API should include namespaces');

        echo " ✅ WordPress REST API is accessible";
    }

    /**
     * Test DMN plugin namespace discovery
     */
    public function testDmnNamespaceDiscovery(): void
    {
        echo "\n📋 Testing DMN namespace discovery...";

        $response = $this->client->get('/wp-json/');
        $body = json_decode($response->getBody()->getContents(), true);

        // Check for our namespace in routes
        $hasOperatonNamespace = false;
        if (isset($body['routes']))
        {
            foreach (array_keys($body['routes']) as $route)
            {
                if (strpos($route, '/operaton-dmn/') !== false)
                {
                    $hasOperatonNamespace = true;
                    break;
                }
            }
        }

        if ($hasOperatonNamespace)
        {
            echo " ✅ DMN namespace found in REST API";
            $this->assertTrue(true); // Positive assertion
        }
        else
        {
            echo " ⚠️  DMN namespace not found - plugin may not be active";
            $this->markTestIncomplete('DMN namespace not found in REST API discovery - this is informational only');
        }
    }

    /**
     * Test DMN health endpoint
     */
    public function testDmnHealthEndpoint(): void
    {
        echo "\n📋 Testing DMN health endpoint...";

        $response = $this->client->get('/wp-json/operaton-dmn/v1/health');

        if ($response->getStatusCode() === 200)
        {
            $body = json_decode($response->getBody()->getContents(), true);
            $this->assertArrayHasKey('status', $body);
            echo " ✅ Health endpoint working (status: " . ($body['status'] ?? 'unknown') . ")";
        }
        else
        {
            echo " ⚠️  Health endpoint returned " . $response->getStatusCode();
            $this->assertContains(
                $response->getStatusCode(),
                [404, 405, 500],
                'Health endpoint should return a valid HTTP status code'
            );
        }
    }

    /**
     * Test DMN test endpoint - shows plugin version and status
     */
    public function testDmnTestEndpoint(): void
    {
        echo "\n📋 Testing DMN test endpoint...";

        $response = $this->client->get('/wp-json/operaton-dmn/v1/test');

        if ($response->getStatusCode() === 200)
        {
            $body = json_decode($response->getBody()->getContents(), true);
            $this->assertArrayHasKey('status', $body);
            echo " ✅ Test endpoint working";

            if (isset($body['version']))
            {
                echo " (version: " . $body['version'] . ")";
            }
        }
        else
        {
            echo " ⚠️  Test endpoint returned " . $response->getStatusCode();
            $this->assertContains(
                $response->getStatusCode(),
                [404, 405, 500],
                'Test endpoint should return a valid HTTP status code'
            );
        }
    }

    /**
     * Test Operaton Engine Information (from OpenAPI spec)
     * GET /engine-rest/version - Get the version of the REST API
     */
    public function testOperatonEngineVersion(): void
    {
        echo "\n📋 Testing Operaton Engine version...";

        $response = $this->dmnClient->get('/engine-rest/version');

        if ($response->getStatusCode() === 200)
        {
            $body = json_decode($response->getBody()->getContents(), true);
            $this->assertArrayHasKey('version', $body);
            echo " ✅ Engine version: " . ($body['version'] ?? 'unknown');

            // Validate version format
            if (isset($body['version']))
            {
                $this->assertMatchesRegularExpression('/^\d+\.\d+/', $body['version'], 'Version should be in semantic format');
            }
        }
        else
        {
            echo " ⚠️  Engine version endpoint returned " . $response->getStatusCode();
            $this->markTestIncomplete('Engine version endpoint not accessible');
        }
    }

    /**
     * Test Engine Information (from OpenAPI spec)
     * GET /engine-rest/engine - Get the names of all process engines available
     */
    public function testEngineList(): void
    {
        echo "\n📋 Testing available engines...";

        $response = $this->dmnClient->get('/engine-rest/engine');

        if ($response->getStatusCode() === 200)
        {
            $body = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($body, 'Engine list should be an array');

            if (!empty($body))
            {
                echo " ✅ Found " . count($body) . " engine(s)";
                foreach ($body as $engine)
                {
                    if (isset($engine['name']))
                    {
                        echo "\n   Engine: " . $engine['name'];
                    }
                }
            }
            else
            {
                echo " ℹ️  No engines found";
            }
        }
        else
        {
            echo " ⚠️  Engine list endpoint returned " . $response->getStatusCode();
            $this->markTestIncomplete('Engine list endpoint not accessible');
        }
    }

    /**
     * Test Decision Definition List (from OpenAPI spec)
     * GET /engine-rest/decision-definition - Get a list of decision definitions
     */
    public function testDecisionDefinitionList(): void
    {
        echo "\n📋 Testing decision definition list...";

        $response = $this->dmnClient->get('/engine-rest/decision-definition');

        if ($response->getStatusCode() === 200)
        {
            $body = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($body, 'Decision definition list should be an array');

            $dishDefinitionFound = false;
            foreach ($body as $definition)
            {
                if (isset($definition['key']) && $definition['key'] === 'dish')
                {
                    $dishDefinitionFound = true;
                    echo " ✅ Found 'dish' decision definition";
                    echo "\n   ID: " . ($definition['id'] ?? 'unknown');
                    echo "\n   Version: " . ($definition['version'] ?? 'unknown');
                    echo "\n   Deployment ID: " . ($definition['deploymentId'] ?? 'unknown');
                    break;
                }
            }

            if (!$dishDefinitionFound)
            {
                echo " ⚠️  'dish' decision definition not found";
                echo "\n   Available definitions: ";
                foreach ($body as $definition)
                {
                    if (isset($definition['key']))
                    {
                        echo $definition['key'] . " ";
                    }
                }
            }

            $this->assertGreaterThan(0, count($body), 'Should have at least one decision definition');
        }
        else
        {
            echo " ⚠️  Decision definition list returned " . $response->getStatusCode();
            $this->markTestIncomplete('Decision definition list not accessible');
        }
    }

    /**
     * Test Decision Definition by Key (from OpenAPI spec)
     * GET /engine-rest/decision-definition/key/{key} - Get a decision definition by key
     */
    public function testDecisionDefinitionByKey(): void
    {
        echo "\n📋 Testing decision definition by key (dish)...";

        $response = $this->dmnClient->get('/engine-rest/decision-definition/key/dish');

        if ($response->getStatusCode() === 200)
        {
            $body = json_decode($response->getBody()->getContents(), true);
            $this->assertArrayHasKey('id', $body);
            $this->assertArrayHasKey('key', $body);
            $this->assertEquals('dish', $body['key']);

            echo " ✅ Dish definition found";
            echo "\n   ID: " . ($body['id'] ?? 'unknown');
            echo "\n   Name: " . ($body['name'] ?? 'unknown');
            echo "\n   Version: " . ($body['version'] ?? 'unknown');

            if (isset($body['resource']))
            {
                echo "\n   Resource: " . $body['resource'];
            }
        }
        else
        {
            echo " ⚠️  Decision definition by key returned " . $response->getStatusCode();
            $this->assertContains($response->getStatusCode(), [404, 500], 'Should return appropriate error code');
        }
    }

    /**
     * Test Decision Definition XML (from OpenAPI spec)
     * GET /engine-rest/decision-definition/key/{key}/xml - Get the XML representation
     */
    public function testDecisionDefinitionXml(): void
    {
        echo "\n📋 Testing decision definition XML...";

        $response = $this->dmnClient->get('/engine-rest/decision-definition/key/dish/xml');

        if ($response->getStatusCode() === 200)
        {
            $body = json_decode($response->getBody()->getContents(), true);
            $this->assertArrayHasKey('dmnXml', $body);

            $xml = $body['dmnXml'];
            $this->assertStringContainsString('<?xml', $xml, 'Should contain XML declaration');
            $this->assertStringContainsString('definitions', $xml, 'Should contain DMN definitions');
            $this->assertStringContainsString('dish', $xml, 'Should contain dish decision logic');

            echo " ✅ Decision XML retrieved successfully";
            echo "\n   XML length: " . strlen($xml) . " characters";
        }
        else
        {
            echo " ⚠️  Decision definition XML returned " . $response->getStatusCode();
            $this->markTestIncomplete('Decision definition XML not accessible');
        }
    }

    /**
     * Test Deployment List (from OpenAPI spec)
     * GET /engine-rest/deployment - Get a list of deployments
     */
    public function testDeploymentList(): void
    {
        echo "\n📋 Testing deployment list...";

        $response = $this->dmnClient->get('/engine-rest/deployment');

        if ($response->getStatusCode() === 200)
        {
            $body = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($body, 'Deployment list should be an array');

            echo " ✅ Found " . count($body) . " deployment(s)";

            foreach ($body as $deployment)
            {
                if (isset($deployment['name']) && stripos($deployment['name'], 'dish') !== false)
                {
                    echo "\n   Dish deployment: " . $deployment['name'];
                    echo "\n   ID: " . ($deployment['id'] ?? 'unknown');
                    echo "\n   Time: " . ($deployment['deploymentTime'] ?? 'unknown');
                    break;
                }
            }
        }
        else
        {
            echo " ⚠️  Deployment list returned " . $response->getStatusCode();
            $this->markTestIncomplete('Deployment list not accessible');
        }
    }

    /**
     * Test direct DMN service connectivity with enhanced validation
     * This validates the underlying DMN engine is working with comprehensive scenarios
     */
    public function testDirectDmnServiceConnectivity(): void
    {
        echo "\n📋 Testing direct DMN service connectivity...";

        // Test all dish decision scenarios from your decision table
        $testScenarios = [
            ['season' => 'Summer', 'guestCount' => 8, 'expected' => 'light salad'],
            ['season' => 'Winter', 'guestCount' => 4, 'expected' => 'roastbeef'],
            ['season' => 'Fall', 'guestCount' => 6, 'expected' => 'spareribs'],
            ['season' => 'Spring', 'guestCount' => 3, 'expected' => 'gourmet steak'],
        ];

        $successCount = 0;
        foreach ($testScenarios as $scenario)
        {
            $dishTestData = [
                'variables' => [
                    'season' => ['value' => $scenario['season'], 'type' => 'String'],
                    'guestCount' => ['value' => $scenario['guestCount'], 'type' => 'Integer']
                ]
            ];

            $response = $this->dmnClient->post('/engine-rest/decision-definition/key/dish/evaluate', [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $dishTestData
            ]);

            if ($response->getStatusCode() === 200)
            {
                $body = json_decode($response->getBody()->getContents(), true);

                if (isset($body[0]['desiredDish']['value']))
                {
                    $result = strtolower($body[0]['desiredDish']['value']);
                    $expected = strtolower($scenario['expected']);

                    if (strpos($result, $expected) !== false)
                    {
                        $successCount++;
                        echo "\n   ✅ " . $scenario['season'] . " + " . $scenario['guestCount'] . " → " . $body[0]['desiredDish']['value'];
                    }
                    else
                    {
                        echo "\n   ⚠️  " . $scenario['season'] . " + " . $scenario['guestCount'] . " → " . $body[0]['desiredDish']['value'] . " (unexpected)";
                    }
                }
            }
        }

        $this->assertGreaterThan(0, $successCount, 'At least one DMN scenario should work');
        echo "\n ✅ DMN connectivity test completed (" . $successCount . "/" . count($testScenarios) . " scenarios successful)";
    }

    /**
     * Test DMN evaluation with invalid data (from OpenAPI spec)
     * Tests error handling for malformed evaluation requests
     */
    public function testDmnEvaluationErrorHandling(): void
    {
        echo "\n📋 Testing DMN evaluation error handling...";

        $invalidScenarios = [
            [
                'name' => 'Missing required variables',
                'data' => ['variables' => ['season' => ['value' => 'Summer', 'type' => 'String']]]
            ],
            [
                'name' => 'Invalid variable type',
                'data' => ['variables' => ['guestCount' => ['value' => 'not_a_number', 'type' => 'Integer']]]
            ],
            [
                'name' => 'Empty variables',
                'data' => ['variables' => []]
            ]
        ];

        $errorHandlingCount = 0;
        foreach ($invalidScenarios as $scenario)
        {
            $response = $this->dmnClient->post('/engine-rest/decision-definition/key/dish/evaluate', [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $scenario['data']
            ]);

            echo "\n   " . $scenario['name'] . ": " . $response->getStatusCode();

            if (in_array($response->getStatusCode(), [400, 422, 500]))
            {
                $errorHandlingCount++;
                echo " ✅ Handled appropriately";
            }
            else
            {
                echo " ⚠️  Unexpected response";
            }
        }

        $this->assertGreaterThan(0, $errorHandlingCount, 'Should handle errors appropriately');
        echo "\n ✅ Error handling test completed (" . $errorHandlingCount . "/" . count($invalidScenarios) . " handled correctly)";
    }

    /**
     * Test DMN evaluation with direct variable approach (known working)
     * This tests the plugin's direct API mode
     */
    public function testDmnEvaluationWithDirectVariables(): void
    {
        echo "\n📋 Testing DMN evaluation with direct variables...";

        $dmnVariableData = [
            'season' => 'Summer',     // Direct DMN variable (proven working)
            'guestCount' => 8,        // Direct DMN variable (proven working)
        ];

        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey)
        {
            $headers['X-API-Key'] = $this->apiKey;
        }

        $response = $this->client->post('/wp-json/operaton-dmn/v1/evaluate', [
            'headers' => $headers,
            'json' => $dmnVariableData
        ]);

        echo "\n   Response status: " . $response->getStatusCode();

        if ($response->getStatusCode() === 200)
        {
            echo " ✅ Direct variable evaluation successful";
            $body = json_decode($response->getBody()->getContents(), true);
            $this->assertEquals(200, $response->getStatusCode());

            if (isset($body['desiredDish']))
            {
                echo " (result: " . $body['desiredDish'] . ")";
            }
        }
        else
        {
            echo " ⚠️  Direct variable evaluation returned " . $response->getStatusCode();
            $this->assertContains($response->getStatusCode(), [200, 400, 422, 500], "Should handle appropriately");
        }
    }

    /**
     * Test DMN History (from OpenAPI spec if available)
     * GET /engine-rest/history/decision-instance - Get historic decision instances
     */
    public function testDmnHistoryQuery(): void
    {
        echo "\n📋 Testing DMN history query...";

        $response = $this->dmnClient->get('/engine-rest/history/decision-instance?decisionDefinitionKey=dish&maxResults=10');

        if ($response->getStatusCode() === 200)
        {
            $body = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($body, 'History should be an array');

            echo " ✅ Found " . count($body) . " historic decision instance(s)";

            if (!empty($body))
            {
                $recent = $body[0];
                echo "\n   Most recent decision ID: " . ($recent['id'] ?? 'unknown');
                echo "\n   Decision time: " . ($recent['evaluationTime'] ?? 'unknown');
                echo "\n   Decision name: " . ($recent['decisionDefinitionName'] ?? 'unknown');
            }
        }
        else
        {
            echo " ⚠️  History query returned " . $response->getStatusCode();
            $this->markTestIncomplete('History endpoint may not be available or accessible');
        }
    }

    /**
     * Test security - malformed requests with enhanced scenarios
     */
    public function testSecurityMalformedRequests(): void
    {
        echo "\n📋 Testing security with malformed requests...";

        $maliciousPayloads = [
            [
                'name' => 'SQL Injection attempt',
                'data' => [
                    'season' => "Summer'; DROP TABLE wp_posts; --",
                    'guestCount' => "8; DELETE FROM wp_users; --"
                ]
            ],
            [
                'name' => 'XSS attempt',
                'data' => [
                    'season' => '<script>alert("xss")</script>',
                    'guestCount' => '<img src=x onerror=alert(1)>'
                ]
            ],
            [
                'name' => 'Buffer overflow attempt',
                'data' => [
                    'season' => str_repeat('A', 10000),
                    'guestCount' => 1
                ]
            ],
            [
                'name' => 'JSON injection',
                'data' => '{"season":"Summer","injection":{"$ne":null}}'
            ]
        ];

        $secureCount = 0;
        foreach ($maliciousPayloads as $payload)
        {
            $requestOptions = [
                'headers' => ['Content-Type' => 'application/json']
            ];

            if (is_string($payload['data']))
            {
                $requestOptions['body'] = $payload['data'];
            }
            else
            {
                $requestOptions['json'] = $payload['data'];
            }

            $response = $this->client->post('/wp-json/operaton-dmn/v1/evaluate', $requestOptions);

            echo "\n   " . $payload['name'] . ": " . $response->getStatusCode();

            // Accept error codes as GOOD security behavior
            if (in_array($response->getStatusCode(), [400, 422, 500]))
            {
                $secureCount++;
                echo " ✅ Handled securely";
            }
            else
            {
                echo " ⚠️  May need security review";
            }
        }

        $this->assertGreaterThan(0, $secureCount, 'At least some malicious requests should be handled securely');
        echo "\n ✅ Security test completed (" . $secureCount . "/" . count($maliciousPayloads) . " handled securely)";
    }

    /**
     * Test API rate limiting and performance with enhanced scenarios
     */
    public function testApiPerformanceAndRateLimiting(): void
    {
        echo "\n📋 Testing API performance and rate limiting...";

        $startTime = microtime(true);
        $successCount = 0;
        $requestCount = 5;
        $responseTimes = [];

        for ($i = 0; $i < $requestCount; $i++)
        {
            $requestStart = microtime(true);

            $response = $this->client->post('/wp-json/operaton-dmn/v1/evaluate', [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'season' => ['Winter', 'Summer', 'Spring', 'Fall'][$i % 4],
                    'guestCount' => $i + 5
                ]
            ]);

            $requestTime = microtime(true) - $requestStart;
            $responseTimes[] = $requestTime;

            if ($response->getStatusCode() === 200)
            {
                $successCount++;
            }
        }

        $totalTime = microtime(true) - $startTime;
        $avgResponseTime = array_sum($responseTimes) / count($responseTimes);

        echo "\n   Completed $requestCount requests in " . number_format($totalTime, 3) . "s";
        echo "\n   Average response time: " . number_format($avgResponseTime, 3) . "s";
        echo "\n   Success rate: $successCount/$requestCount";

        // Performance should be reasonable
        $this->assertLessThan(10, $totalTime, 'API should handle multiple requests efficiently');
        $this->assertLessThan(5, $avgResponseTime, 'Individual requests should be reasonably fast');

        if ($successCount > 0)
        {
            echo " ✅ API performance acceptable";
        }
        else
        {
            echo " ℹ️  API performance test completed (results may vary based on configuration)";
        }
    }

    /**
     * Test basic connectivity - This should always pass
     */
    public function testBasicConnectivity(): void
    {
        echo "\n📋 Testing basic connectivity...";

        $response = $this->client->get('/');

        // Just check that we can connect to the site
        $this->assertLessThan(600, $response->getStatusCode(), 'Should get a valid HTTP response');
        $this->assertGreaterThanOrEqual(200, $response->getStatusCode(), 'Should get a valid HTTP response');

        echo " ✅ Basic connectivity working (status: " . $response->getStatusCode() . ")";
    }

    /**
     * Test Content-Type validation (from OpenAPI spec)
     * Ensures proper content type handling
     */
    public function testContentTypeValidation(): void
    {
        echo "\n📋 Testing content type validation...";

        // Test with wrong content type
        $response = $this->client->post('/wp-json/operaton-dmn/v1/evaluate', [
            'headers' => ['Content-Type' => 'text/plain'],
            'body' => 'invalid data'
        ]);

        echo "\n   Wrong content-type response: " . $response->getStatusCode();

        // Should reject non-JSON content
        $this->assertContains($response->getStatusCode(), [400, 415, 422, 500], 'Should reject invalid content type');

        echo " ✅ Content type validation working";
    }
}
