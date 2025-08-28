#!/bin/bash

# Load environment variables from .env.testing file
# This script can be sourced before running tests to ensure consistent environment

ENV_FILE=".env.testing"

if [ ! -f "$ENV_FILE" ]; then
    echo "Creating default $ENV_FILE file..."
    cat > "$ENV_FILE" << 'EOF'
# Environment configuration for testing
DMN_TEST_URL=https://owc-gemeente.test.open-regels.nl
DMN_ENGINE_URL=http://localhost:8080
DMN_API_KEY=
TEST_ENV=development
EOF
    echo "✅ Created $ENV_FILE with default values"
fi

# Load environment variables
if [ -f "$ENV_FILE" ]; then
    echo "📋 Loading environment variables from $ENV_FILE"

    # Export variables from .env.testing
    while IFS= read -r line; do
        # Skip comments and empty lines
        if [[ "$line" =~ ^[[:space:]]*# ]] || [[ -z "$line" ]]; then
            continue
        fi

        # Extract key=value pairs
        if [[ "$line" =~ ^[[:space:]]*([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]]; then
            key="${BASH_REMATCH[1]}"
            value="${BASH_REMATCH[2]}"

            # Remove quotes if present
            value="${value%\"}"
            value="${value#\"}"
            value="${value%\'}"
            value="${value#\'}"

            # Export the variable
            export "$key"="$value"
            echo "   $key=$value"
        fi
    done < "$ENV_FILE"

    echo "✅ Environment variables loaded"
else
    echo "❌ $ENV_FILE file not found"
    exit 1
fi

echo ""
echo "🔧 Environment Summary:"
echo "   WordPress Test URL: ${DMN_TEST_URL:-not set}"
echo "   DMN Engine URL: ${DMN_ENGINE_URL:-not set}"
echo "   API Key: ${DMN_API_KEY:+configured}"
echo "   Test Environment: ${TEST_ENV:-not set}"
echo ""
