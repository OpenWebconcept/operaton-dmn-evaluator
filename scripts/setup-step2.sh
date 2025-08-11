#!/bin/bash

# Step 2: Extended Mock DMN Service Setup
# Save this as: scripts/setup-step2.sh

set -e

echo "🏗️ Step 2: Setting up Extended Mock DMN Service"
echo "=============================================="

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
log_success() { echo -e "${GREEN}✅ $1${NC}"; }
log_step() { echo -e "${YELLOW}🔹 $1${NC}"; }

# Check if we're in the right directory
if [ ! -f "operaton-dmn-plugin.php" ]; then
    echo "❌ Please run this script from the plugin root directory"
    exit 1
fi

log_step "Creating test directory structure"
mkdir -p tests/fixtures
mkdir -p tests/helpers
mkdir -p tests/unit

log_step "Creating Extended Mock DMN Service files"
# Note: You need to copy the PHP files from the artifacts above to these locations:
# - ExtendedMockDmnService.php -> tests/fixtures/
# - MockServiceTestHelper.php -> tests/helpers/
# - MockServiceTest.php -> tests/unit/

echo "📁 Please copy these files from the artifacts:"
echo "   1. ExtendedMockDmnService.php -> tests/fixtures/"
echo "   2. MockServiceTestHelper.php -> tests/helpers/"
echo "   3. MockServiceTest.php -> tests/unit/"

log_step "Testing the mock service"
if [ -f "tests/unit/MockServiceTest.php" ]; then
    composer run test:unit -- --filter MockServiceTest
    if [ $? -eq 0 ]; then
        log_success "Mock service tests passed!"
    else
        echo "⚠️  Mock service tests failed - please check the implementation"
    fi
else
    echo "⚠️  MockServiceTest.php not found - please copy the files first"
fi

log_step "Adding composer scripts for mock service"
echo ""
echo "📋 Add these scripts to your composer.json:"
echo ""
cat << 'EOF'
{
    "scripts": {
        "test:mock": "phpunit tests/unit/MockServiceTest.php",
        "mock:demo": "php -r \"require_once 'tests/fixtures/ExtendedMockDmnService.php'; require_once 'tests/helpers/MockServiceTestHelper.php'; use Operaton\\DMN\\Tests\\Helpers\\MockServiceTestHelper; \\$helper = new MockServiceTestHelper(); \\$report = \\$helper->generateTestReport(); echo json_encode(\\$report, JSON_PRETTY_PRINT);\""
    }
}
EOF

echo ""
echo "🎉 Step 2 Complete! Extended Mock DMN Service is ready."
echo ""
echo "📋 What you can do now:"
echo "   composer run test:mock          # Test the mock service"
echo "   composer run mock:demo          # See mock service in action"
echo ""
echo "🔄 Next: We'll implement REST API Integration Tests"
echo ""
echo "📝 Manual steps needed:"
echo "   1. Copy the 3 PHP files from the artifacts to the correct locations"
echo "   2. Add the composer scripts shown above"
echo "   3. Run 'composer run test:mock' to verify everything works"
