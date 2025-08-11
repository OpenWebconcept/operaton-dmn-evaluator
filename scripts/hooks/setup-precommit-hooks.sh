#!/bin/bash

# Step 1: Pre-commit Hooks Implementation
# Save this as: scripts/hooks/setup-precommit-hooks.sh

set -e

echo "🔧 Step 1: Setting up Pre-commit Hooks for Operaton DMN Evaluator"
echo "================================================================="

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

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    echo "❌ Not in a git repository. Please initialize git first:"
    echo "   git init"
    echo "   git add ."
    echo "   git commit -m 'Initial commit'"
    exit 1
fi

log_step "Creating hooks directory structure"
mkdir -p scripts/hooks
mkdir -p .git/hooks

log_step "Creating PHP syntax checker"
cat > scripts/hooks/check-php-syntax.sh << 'EOF'
#!/bin/bash
echo "Checking PHP syntax..."

STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)

if [ -z "$STAGED_PHP_FILES" ]; then
    echo "No PHP files to check."
    exit 0
fi

SYNTAX_ERRORS=false

for file in $STAGED_PHP_FILES; do
    if [ -f "$file" ]; then
        php -l "$file" > /dev/null 2>&1
        if [ $? -ne 0 ]; then
            echo "❌ Syntax error in: $file"
            php -l "$file"
            SYNTAX_ERRORS=true
        else
            echo "✅ $file"
        fi
    fi
done

if [ "$SYNTAX_ERRORS" = true ]; then
    echo "💥 PHP syntax errors found. Please fix them before committing."
    exit 1
fi

echo "🎉 All PHP files have valid syntax!"
exit 0
EOF

chmod +x scripts/hooks/check-php-syntax.sh
log_success "PHP syntax checker created"

log_step "Creating simple pre-commit hook"
cat > .git/hooks/pre-commit << 'EOF'
#!/bin/bash

echo "🔍 Running pre-commit checks..."

# PHP Syntax Check
if ! scripts/hooks/check-php-syntax.sh; then
    echo "❌ Pre-commit checks failed"
    exit 1
fi

# Basic file size check
LARGE_FILES=$(git diff --cached --name-only | xargs ls -la 2>/dev/null | awk '$5 > 1048576 {print $9 " (" $5 " bytes)"}')
if [ -n "$LARGE_FILES" ]; then
    echo "❌ Large files detected (>1MB):"
    echo "$LARGE_FILES"
    echo "Please remove large files or add them to .gitignore"
    exit 1
fi

echo "✅ Pre-commit checks passed!"
exit 0
EOF

chmod +x .git/hooks/pre-commit
log_success "Basic pre-commit hook created"

log_step "Creating hook management script"
cat > scripts/hooks/manage-hooks.sh << 'EOF'
#!/bin/bash

case "$1" in
    "enable")
        chmod +x .git/hooks/*
        echo "✅ Hooks enabled"
        ;;
    "disable")
        chmod -x .git/hooks/*
        echo "✅ Hooks disabled"
        ;;
    "status")
        echo "📋 Hook Status:"
        for hook in .git/hooks/*; do
            if [ -x "$hook" ]; then
                echo "✅ $(basename "$hook") - enabled"
            else
                echo "❌ $(basename "$hook") - disabled"
            fi
        done
        ;;
    "test")
        echo "🧪 Testing pre-commit hook..."
        if .git/hooks/pre-commit; then
            echo "✅ Pre-commit hook test passed"
        else
            echo "❌ Pre-commit hook test failed"
        fi
        ;;
    *)
        echo "Usage: $0 {enable|disable|status|test}"
        ;;
esac
EOF

chmod +x scripts/hooks/manage-hooks.sh
log_success "Hook management script created"

echo ""
echo "🎉 Step 1 Complete! Pre-commit hooks are now set up."
echo ""
echo "📋 What you can do now:"
echo "   scripts/hooks/manage-hooks.sh status  # Check hook status"
echo "   scripts/hooks/manage-hooks.sh test    # Test the hooks"
echo "   git add . && git commit               # Test with a real commit"
echo ""
echo "🔄 Next: We'll implement the Extended Mock DMN Service"
