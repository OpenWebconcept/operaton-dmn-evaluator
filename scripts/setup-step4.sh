#!/bin/bash

# Step 4: Load Testing Setup
# Save this as: scripts/setup-step4.sh

set -e

echo "⚡ Step 4: Setting up Load Testing with K6"
echo "========================================"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
log_success() { echo -e "${GREEN}✅ $1${NC}"; }
log_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
log_error() { echo -e "${RED}❌ $1${NC}"; }
log_step() { echo -e "${YELLOW}🔹 $1${NC}"; }

# Check if we're in the right directory
if [ ! -f "operaton-dmn-plugin.php" ]; then
    log_error "Please run this script from the plugin root directory"
    exit 1
fi

log_step "Creating load testing directory"
mkdir -p tests/load
mkdir -p test-results

log_step "Checking K6 installation"
if command -v k6 &> /dev/null; then
    K6_VERSION=$(k6 version)
    log_success "K6 is installed: $K6_VERSION"
else
    log_warning "K6 is not installed. Please install it:"
    echo ""
    echo "📋 Installation options:"
    echo "  macOS:     brew install k6"
    echo "  Ubuntu:    sudo apt-get install k6"
    echo "  Windows:   choco install k6"
    echo "  Manual:    https://k6.io/docs/getting-started/installation/"
    echo ""
    echo "❌ Cannot continue without K6. Please install it and run this script again."
    exit 1
fi

log_step "Creating load test configuration"
echo "📁 Please copy dmn-load-test.js to tests/load/"

log_step "Setting up load testing environment"
cat > tests/load/.env << 'EOF'
# Load Testing Environment Configuration
BASE_URL=https://owc-gemeente.test.open-regels.nl
DMN_API_KEY=
TEST_TYPE=mixed
EOF

log_success "Load testing environment configuration created"

log_step "Adding package.json scripts for load testing"
echo ""
echo "📋 Add these scripts to your package.json:"
echo ""
cat << 'EOF'
{
  "scripts": {
    "test:load": "k6 run tests/load/dmn-load-test.js",
    "test:load:smoke": "k6 run tests/load/dmn-load-test.js --env TEST_TYPE=health_only",
    "test:load:evaluation": "k6 run tests/load/dmn-load-test.js --env TEST_TYPE=evaluation_only",
    "test:load:report": "k6 run tests/load/dmn-load-test.js --out json=test-results/load-test-results.json"
  }
}
EOF

log_step "Testing load testing setup"
if [ -f "tests/load/dmn-load-test.js" ]; then
    echo "🧪 Running a quick smoke test..."

    # Set environment variables
    export BASE_URL=https://owc-gemeente.test.open-regels.nl
    export TEST_TYPE=health_only

    # Run a very quick test
    if k6 run --vus 1 --duration 10s tests/load/dmn-load-test.js; then
        log_success "Load testing setup working!"
    else
        log_warning "Load test had issues - this might be expected if the target site isn't accessible"
    fi
else
    log_warning "dmn-load-test.js not found - please copy the file first"
fi

echo ""
echo "🎉 Step 4 Complete! Load Testing with K6 is ready."
echo ""
echo "📋 What you can do now:"
echo "   npm run test:load              # Run full load test"
echo "   npm run test:load:smoke        # Run health check only"
echo "   npm run test:load:evaluation   # Run DMN evaluation load test"
echo "   npm run test:load:report       # Run with JSON report output"
echo ""
echo "🔧 Configuration:"
echo "   • Edit tests/load/.env to change target URL or add API key"
echo "   • Target URL: https://owc-gemeente.test.open-regels.nl"
echo "   • Test scenarios: health checks and DMN evaluations"
echo ""
echo "🔄 Next: We'll implement Chaos Engineering Tests"
echo ""
echo "📝 Manual steps needed:"
echo "   1. Copy dmn-load-test.js to tests/load/"
echo "   2. Add the npm scripts shown above to package.json"
echo "   3. Run 'npm run test:load:smoke' for a quick test"
echo ""
echo "💡 Pro tips:"
echo "   • Start with smoke tests before running full load tests"
echo "   • Monitor your server during load tests"
echo "   • Use different TEST_TYPE values to focus on specific areas"
