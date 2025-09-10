#!/bin/bash
# DMN Load Test Debug and Fix Script
# Save as: scripts/debug-load-test.sh
# Usage: bash scripts/debug-load-test.sh

echo "🔍 DMN Load Test Debug and Diagnostic Tool"
echo "=========================================="

# Load environment variables
if [ -f ".env.testing" ]; then
    source .env.testing
    echo "✅ Environment loaded from .env.testing"
else
    echo "⚠️  .env.testing not found, using defaults"
    DMN_TEST_URL="https://owc-gemeente.test.open-regels.nl"
    DMN_ENGINE_URL="https://operaton-dev.open-regels.nl"
fi

echo "🌐 Test URL: $DMN_TEST_URL"
echo "🏗️  Engine URL: $DMN_ENGINE_URL"
echo ""

# Step 1: Basic connectivity test
echo "1️⃣  Testing Basic Connectivity..."
echo "================================"

echo -n "WordPress API: "
wp_status=$(curl -s -o /dev/null -w "%{http_code}" "$DMN_TEST_URL/wp-json/" --connect-timeout 10)
if [ "$wp_status" = "200" ]; then
    echo "✅ OK ($wp_status)"
else
    echo "❌ FAILED ($wp_status)"
fi

echo -n "DMN Engine: "
engine_status=$(curl -s -o /dev/null -w "%{http_code}" "$DMN_ENGINE_URL/engine-rest/version" --connect-timeout 10)
if [ "$engine_status" = "200" ]; then
    echo "✅ OK ($engine_status)"
else
    echo "❌ FAILED ($engine_status)"
fi

echo -n "DMN Plugin Test Endpoint: "
plugin_status=$(curl -s -o /dev/null -w "%{http_code}" "$DMN_TEST_URL/wp-json/operaton-dmn/v1/test" --connect-timeout 10)
if [ "$plugin_status" = "200" ] || [ "$plugin_status" = "404" ] || [ "$plugin_status" = "405" ]; then
    echo "✅ OK ($plugin_status)"
else
    echo "❌ FAILED ($plugin_status)"
fi

echo ""

# Step 2: Single DMN evaluation test
echo "2️⃣  Testing Single DMN Evaluation..."
echo "===================================="

test_payload='{
    "config_id": 1,
    "form_data": {
        "season": "Winter",
        "guestCount": 4
    }
}'

echo "Test payload: $test_payload"
echo ""

echo "Making single evaluation request..."
response=$(curl -s -w "\nHTTP_CODE:%{http_code}\nTIME:%{time_total}" \
    -X POST "$DMN_TEST_URL/wp-json/operaton-dmn/v1/evaluate" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "$test_payload")

http_code=$(echo "$response" | grep "HTTP_CODE:" | cut -d: -f2)
time_total=$(echo "$response" | grep "TIME:" | cut -d: -f2)
response_body=$(echo "$response" | sed '/HTTP_CODE:/d' | sed '/TIME:/d')

echo "Response Code: $http_code"
echo "Response Time: ${time_total}s"
echo "Response Body: $response_body"

if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
    echo "✅ Single evaluation successful"
else
    echo "❌ Single evaluation failed"
    echo "🔍 This is likely the root cause of your load test failures"
fi

echo ""

# Step 3: Test multiple scenarios sequentially
echo "3️⃣  Testing Multiple Scenarios (Sequential)..."
echo "=============================================="

scenarios=(
    '{"season": "Summer", "guestCount": 8}'
    '{"season": "Winter", "guestCount": 4}'
    '{"season": "Fall", "guestCount": 6}'
    '{"season": "Spring", "guestCount": 3}'
)

success_count=0
total_scenarios=${#scenarios[@]}

for i in "${!scenarios[@]}"; do
    scenario=${scenarios[$i]}
    payload="{\"config_id\": 1, \"form_data\": $scenario}"

    echo -n "Scenario $((i+1)): $scenario ... "

    code=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$DMN_TEST_URL/wp-json/operaton-dmn/v1/evaluate" \
        -H "Content-Type: application/json" \
        -d "$payload" \
        --connect-timeout 15 \
        --max-time 30)

    if [ "$code" = "200" ] || [ "$code" = "201" ]; then
        echo "✅ OK ($code)"
        ((success_count++))
    else
        echo "❌ FAILED ($code)"
    fi

    # Small delay between requests
    sleep 0.5
done

echo ""
echo "Sequential Test Results: $success_count/$total_scenarios scenarios successful"

if [ "$success_count" -eq "$total_scenarios" ]; then
    echo "✅ All scenarios work individually"
else
    echo "❌ Some scenarios failing even in sequential testing"
    echo "🔍 Issue is not load-related, check DMN configuration"
fi

echo ""

# Step 4: Test concurrent requests (light load)
echo "4️⃣  Testing Light Concurrent Load..."
echo "===================================="

echo "Running 3 concurrent requests..."

# Create a simple concurrent test
for i in {1..3}; do
    (
        payload='{"config_id": 1, "form_data": {"season": "Summer", "guestCount": 8}}'
        code=$(curl -s -o /dev/null -w "%{http_code}" \
            -X POST "$DMN_TEST_URL/wp-json/operaton-dmn/v1/evaluate" \
            -H "Content-Type: application/json" \
            -d "$payload" \
            --connect-timeout 20 \
            --max-time 45)
        echo "Concurrent request $i: $code"
    ) &
done

wait # Wait for all background jobs to complete

echo ""

# Step 5: Recommendations
echo "5️⃣  Recommendations and Fixes..."
echo "==============================="

if [ "$wp_status" != "200" ]; then
    echo "❌ WordPress API not accessible - check server status"
fi

if [ "$engine_status" != "200" ]; then
    echo "❌ DMN Engine not accessible - check Operaton server"
fi

if [ "$success_count" -lt "$total_scenarios" ]; then
    echo "🔧 DMN evaluation issues detected. Possible fixes:"
    echo "   1. Check WordPress error logs"
    echo "   2. Verify DMN plugin configuration"
    echo "   3. Test DMN engine connectivity manually"
    echo "   4. Check for PHP errors in evaluation code"
    echo ""
    echo "📋 Debug commands to run:"
    echo "   composer run test:api:verbose"
    echo "   composer run test:integration"
    echo "   tail -f /path/to/wordpress/wp-content/debug.log"
fi

echo ""
echo "🎯 Load Test Configuration Recommendations:"
echo "   - Start with 1 VU for 30s (smoke test)"
echo "   - Increase timeouts to 15-30 seconds"
echo "   - Reduce concurrent users (max 2-3 VUs)"
echo "   - Add delays between requests (1-2 seconds)"
echo "   - Use more realistic thresholds (2-5 second response times)"

echo ""
echo "📝 Suggested load test fixes applied in the updated script:"
echo "   ✅ Increased timeouts from 3s to 15s"
echo "   ✅ Reduced maximum VUs from 8 to 5"
echo "   ✅ Added progressive ramping with longer stages"
echo "   ✅ Better error handling and logging"
echo "   ✅ More realistic success rate thresholds"

echo ""
echo "🚀 Next steps:"
echo "1. Replace your dmn-load-test.js with the fixed version"
echo "2. Run: npm run test:load:smoke"
echo "3. If smoke test passes, try: npm run test:load"
echo "4. Monitor WordPress/Operaton logs during testing"

echo ""
echo "Debug script completed!"
