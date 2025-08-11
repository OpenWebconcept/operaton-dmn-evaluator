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
