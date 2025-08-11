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
