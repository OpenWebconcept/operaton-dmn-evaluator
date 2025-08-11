#!/bin/bash

# Step 3: REST API Integration Tests Setup
# Save this as: scripts/setup-step3.sh

set -e

echo "🔗 Step 3: Setting up REST API Integration Tests"
echo "==============================================="

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

log_step "Creating integration test directory"
mkdir -p tests/integration

log_step "Installing Guzzle HTTP client"
if ! composer show guzzlehttp/guzzle > /dev/null 2>&1; then
    composer require --dev guzzlehttp/guzzle
    log_success "Guzzle HTTP client installed"
else
    log_success "Guzzle HTTP client already installed"
fi

log_step "Setting up environment configuration"
cat > .env.testing << 'EOF'
# Environment configuration for testing
DMN_TEST_URL=https://owc-gemeente.test.open-regels.nl
DMN_API_KEY=
TEST_ENV=development
EOF

log_success "Environment configuration created (.env.testing)"

log_step "Creating integration test configuration"
echo "📁 Please copy RestApiIntegrationTest.php to tests/integration/"

log_step "Adding composer scripts for integration tests"
echo ""
echo "📋 Add these scripts to your composer.json:"
echo ""
cat << 'EOF'
{
    "scripts": {
        "test:integration": "phpunit tests/integration/",
        "test:api": "phpunit tests/integration/RestApiIntegrationTest.php",
        "test:api:verbose": "phpunit tests/integration/RestApiIntegrationTest.php --verbose"
    }
}
EOF

log_step "Testing the integration test setup"
if [ -f "tests/integration/RestApiIntegrationTest.php" ]; then
    echo "🧪 Running a quick integration test..."

    # Set environment variables for the test
    export DMN_TEST_URL=https://owc-gemeente.test.open-regels.nl
    export DMN_API_KEY=""

    # Check if the composer script exists before running it
    if composer run --list | grep -q "test:api"; then
        # Run the composer script if it exists
        composer run test:api -- --filter testWordPressRestApiAccessibility

        if [ $? -eq 0 ]; then
            log_success "Integration test setup working!"
        else
            echo "⚠️  Integration test had issues - this might be expected if the target site isn't accessible"
        fi
    else
        # Run PHPUnit directly if composer script doesn't exist yet
        echo "   Running PHPUnit directly (composer script not added yet)..."
        if ./vendor/bin/phpunit tests/integration/RestApiIntegrationTest.php --filter testWordPressRestApiAccessibility --verbose; then
            log_success "Integration test setup working!"
        else
            echo "⚠️  Integration test had issues - this might be expected if the target site isn't accessible"
            echo "   This is normal for the initial setup"
        fi
    fi
else
    echo "⚠️  RestApiIntegrationTest.php not found - please copy the file first"
fi

echo ""
echo "🎉 Step 3 Complete! REST API Integration Tests are ready."
echo ""
echo "📋 What you can do now:"
echo "   composer run test:integration    # Run all integration tests"
echo "   composer run test:api           # Run just the API tests"
echo "   composer run test:api:verbose   # Run with detailed output"
echo ""
echo "🔧 Configuration:"
echo "   • Edit .env.testing to change target URL or add API key"
echo "   • Target URL: https://owc-gemeente.test.open-regels.nl"
echo "   • API Key: Not configured (tests will run without authentication)"
echo ""
echo "🔄 Next: We'll implement Load Testing with K6"
echo ""
echo "📝 Manual steps needed:"
echo "   1. Copy RestApiIntegrationTest.php to tests/integration/"
echo "   2. Add the composer scripts shown above"
echo "   3. Run 'composer run test:api:verbose' to see detailed test output"
