#!/bin/bash

# Step 6: Comprehensive Test Suite Setup
# Save this as: scripts/setup-step6.sh

set -e

echo "🎯 Step 6: Setting up Comprehensive Test Suite Integration"
echo "========================================================"

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

log_step "Creating comprehensive test suite script"
echo "📁 Please copy run-comprehensive-tests.sh to scripts/"

log_step "Making the script executable"
if [ -f "scripts/run-comprehensive-tests.sh" ]; then
    chmod +x scripts/run-comprehensive-tests.sh
    log_success "Script is now executable"
else
    echo "⚠️  run-comprehensive-tests.sh not found - please copy the file first"
fi

log_step "Adding composer and npm scripts"
echo ""
echo "📋 Add these scripts to your composer.json:"
echo ""
cat << 'EOF'
{
    "scripts": {
        "test:comprehensive": "bash scripts/run-comprehensive-tests.sh",
        "test:all": "bash scripts/run-comprehensive-tests.sh --load --chaos",
        "test:quick": "bash scripts/run-comprehensive-tests.sh --no-integration",
        "ci": "bash scripts/run-comprehensive-tests.sh"
    }
}
EOF

echo ""
echo "📋 Add these scripts to your package.json:"
echo ""
cat << 'EOF'
{
    "scripts": {
        "test:comprehensive": "bash scripts/run-comprehensive-tests.sh",
        "test:all": "bash scripts/run-comprehensive-tests.sh --load --chaos",
        "test:quick": "bash scripts/run-comprehensive-tests.sh --no-integration",
        "test:ci": "bash scripts/run-comprehensive-tests.sh"
    }
}
EOF

log_step "Creating a convenient test runner alias"
cat > run-tests.sh << 'EOF'
#!/bin/bash
# Convenient test runner - shortcuts to common test combinations

case "$1" in
    "quick")
        echo "🚀 Running quick tests (unit only)..."
        scripts/run-comprehensive-tests.sh --no-integration
        ;;
    "standard")
        echo "🚀 Running standard tests (unit + integration)..."
        scripts/run-comprehensive-tests.sh
        ;;
    "full")
        echo "🚀 Running full test suite (including load tests)..."
        scripts/run-comprehensive-tests.sh --load
        ;;
    "extreme")
        echo "🚀 Running extreme test suite (including chaos)..."
        scripts/run-comprehensive-tests.sh --load --chaos
        ;;
    "help"|*)
        echo "Test Runner for Operaton DMN Evaluator"
        echo ""
        echo "Usage: $0 {quick|standard|full|extreme}"
        echo ""
        echo "  quick     - Unit tests only (fastest)"
        echo "  standard  - Unit + Integration tests (recommended)"
        echo "  full      - Standard + Load tests"
        echo "  extreme   - Everything including chaos engineering"
        echo ""
        echo "Examples:"
        echo "  $0 quick      # Quick development testing"
        echo "  $0 standard   # Before committing"
        echo "  $0 full       # Before releasing"
        echo "  $0 extreme    # Full resilience testing"
        ;;
esac
EOF

chmod +x run-tests.sh
log_success "Test runner alias created: ./run-tests.sh"

log_step "Testing the comprehensive test suite"
if [ -f "scripts/run-comprehensive-tests.sh" ]; then
    echo "🧪 Running a quick test of the comprehensive suite..."

    # Run just unit tests to verify the setup
    if scripts/run-comprehensive-tests.sh --no-integration --no-reports; then
        log_success "Comprehensive test suite is working!"
    else
        echo "⚠️  Test suite had issues - this might be expected if some components aren't set up yet"
    fi
else
    echo "⚠️  run-comprehensive-tests.sh not found - please copy the file first"
fi

echo ""
echo "🎉 Step 6 Complete! Comprehensive Test Suite Integration is ready."
echo ""
echo "📋 What you can do now:"
echo "   ./run-tests.sh quick           # Quick unit tests"
echo "   ./run-tests.sh standard        # Unit + integration tests"
echo "   ./run-tests.sh full            # Include load tests"
echo "   ./run-tests.sh extreme         # Everything including chaos"
echo ""
echo "   composer run test:comprehensive # Standard comprehensive tests"
echo "   npm run test:all               # Full test suite with load & chaos"
echo ""
echo "🔧 Configuration:"
echo "   • Set DMN_TEST_URL environment variable to change target"
echo "   • Set DMN_API_KEY environment variable for authenticated tests"
echo "   • Results are saved in test-results/ directory"
echo ""
echo "🎯 IMPLEMENTATION COMPLETE!"
echo ""
echo "You now have a comprehensive testing foundation with:"
echo "   ✅ Pre-commit hooks for code quality"
echo "   ✅ Extended mock DMN service with realistic test data"
echo "   ✅ REST API integration tests for live environment validation"
echo "   ✅ K6 load testing for performance validation"
echo "   ✅ Chaos engineering for resilience testing"
echo "   ✅ Comprehensive test suite orchestration"
echo ""
echo "📝 Manual steps needed:"
echo "   1. Copy run-comprehensive-tests.sh to scripts/"
echo "   2. Add the composer and npm scripts shown above"
echo "   3. Run './run-tests.sh standard' to test everything"
echo ""
echo "🚀 Next steps:"
echo "   • Run './run-tests.sh standard' to verify everything works"
echo "   • Set up CI/CD integration using these test scripts"
echo "   • Configure environment-specific test targets"
echo "   • Schedule regular load and chaos testing"
