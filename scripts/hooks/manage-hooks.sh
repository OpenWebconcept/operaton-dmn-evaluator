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
