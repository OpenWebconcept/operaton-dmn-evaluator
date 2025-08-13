#!/bin/bash
# File: scripts/create-release.sh
# Script to create a release package for the Operaton DMN Evaluator plugin

set -e

# Configuration
PLUGIN_NAME="operaton-dmn-evaluator"
VERSION="$1"
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BUILD_DIR="$PROJECT_ROOT/build"
RELEASE_DIR="$BUILD_DIR/release"

# Check if version is provided
if [ -z "$VERSION" ]; then
    echo "Usage: $0 <version>"
    echo "Example: $0 1.0.0-beta.6"
    exit 1
fi

echo "Creating release package for version: $VERSION"
echo "Project root: $PROJECT_ROOT"

# Clean and create build directory
rm -rf "$BUILD_DIR"
mkdir -p "$RELEASE_DIR/$PLUGIN_NAME"

# Navigate to project root
cd "$PROJECT_ROOT"

# Copy plugin files (exclude development files)
echo "Copying plugin files..."

# Core plugin files
cp operaton-dmn-plugin.php "$RELEASE_DIR/$PLUGIN_NAME/" || { echo "Main plugin file not found!"; exit 1; }

# Copy directories if they exist
for dir in assets templates includes languages; do
    if [ -d "$dir" ]; then
        echo "Copying $dir/ directory..."
        cp -r "$dir/" "$RELEASE_DIR/$PLUGIN_NAME/"
    else
        echo "Directory $dir/ not found - skipping"
    fi
done

# Copy optional files
for file in README.md LICENSE CHANGELOG.md composer.json; do
    if [ -f "$file" ]; then
        echo "Copying $file..."
        cp "$file" "$RELEASE_DIR/$PLUGIN_NAME/"
    else
        echo "File $file not found - skipping"
    fi
done

# Create a basic readme.txt for WordPress compatibility
cat > "$RELEASE_DIR/$PLUGIN_NAME/readme.txt" << EOF
=== Operaton DMN Evaluator ===
Contributors: stevengort
Tags: gravity-forms, dmn, decision-engine, operaton
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: $VERSION
License: EUPL v1.2

WordPress plugin to integrate Gravity Forms with Operaton DMN decision tables for dynamic form evaluations.

== Description ==

This plugin allows you to integrate Operaton DMN (Decision Model and Notation) decision engines with Gravity Forms, enabling dynamic form evaluation based on business rules.

== Installation ==

1. Upload the plugin files to the \`/wp-content/plugins/operaton-dmn-evaluator/\` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Operaton DMN menu to configure your decision endpoints

== Changelog ==

= $VERSION =
* See GitLab releases for detailed changelog
EOF

# Update version in main plugin file
echo "Updating version in plugin file..."
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s/Version: .*/Version: $VERSION/" "$RELEASE_DIR/$PLUGIN_NAME/operaton-dmn-plugin.php"
    sed -i '' "s/define('OPERATON_DMN_VERSION', '.*');/define('OPERATON_DMN_VERSION', '$VERSION');/" "$RELEASE_DIR/$PLUGIN_NAME/operaton-dmn-plugin.php"
else
    # Linux
    sed -i "s/Version: .*/Version: $VERSION/" "$RELEASE_DIR/$PLUGIN_NAME/operaton-dmn-plugin.php"
    sed -i "s/define('OPERATON_DMN_VERSION', '.*');/define('OPERATON_DMN_VERSION', '$VERSION');/" "$RELEASE_DIR/$PLUGIN_NAME/operaton-dmn-plugin.php"
fi

# Verify the version was updated
echo "Verifying version update..."
if grep -q "Version: $VERSION" "$RELEASE_DIR/$PLUGIN_NAME/operaton-dmn-plugin.php"; then
    echo "✓ Plugin header version updated successfully"
else
    echo "✗ Failed to update plugin header version"
    exit 1
fi

if grep -q "define('OPERATON_DMN_VERSION', '$VERSION');" "$RELEASE_DIR/$PLUGIN_NAME/operaton-dmn-plugin.php"; then
    echo "✓ Plugin constant version updated successfully"
else
    echo "✗ Failed to update plugin constant version"
    exit 1
fi

# Create archive
echo "Creating archive..."
cd "$RELEASE_DIR"

# Try multiple archive methods
ARCHIVE_CREATED=false

if command -v zip &> /dev/null; then
    # Use zip if available (preferred for WordPress)
    ARCHIVE_NAME="$PLUGIN_NAME-$VERSION.zip"
    echo "Using zip to create: $ARCHIVE_NAME"
    zip -r "../$ARCHIVE_NAME" "$PLUGIN_NAME/" -x "*.DS_Store*" "*__MACOSX*" "*.git*"
    ARCHIVE_PATH="$BUILD_DIR/$ARCHIVE_NAME"
    ARCHIVE_CREATED=true
elif command -v tar &> /dev/null; then
    # Create tar.gz
    TAR_NAME="$PLUGIN_NAME-$VERSION.tar.gz"
    echo "Zip not available, creating tar.gz: $TAR_NAME"
    tar --exclude="*.DS_Store*" --exclude="*__MACOSX*" --exclude="*.git*" -czf "../$TAR_NAME" "$PLUGIN_NAME/"
    ARCHIVE_PATH="$BUILD_DIR/$TAR_NAME"
    ARCHIVE_NAME="$TAR_NAME"
    ARCHIVE_CREATED=true
    echo "✓ tar.gz archive created successfully"
else
    echo "❌ Neither zip nor tar command found!"
    echo ""
    echo "Files are ready in: $RELEASE_DIR/$PLUGIN_NAME/"
    echo ""
    echo "Manual archive creation steps:"
    echo "  1. Navigate to: $RELEASE_DIR/"
    echo "  2. Right-click on '$PLUGIN_NAME' folder"
    echo "  3. Create compressed archive (ZIP format preferred)"
    echo "  4. Name it: $PLUGIN_NAME-$VERSION.zip"
    echo ""
    echo "Alternative: Install zip command:"
    echo "  Ubuntu/Debian: sudo apt install zip"
    echo "  CentOS/RHEL:   sudo yum install zip"
    echo "  macOS:         brew install zip"
    cd "$PROJECT_ROOT"
    exit 0
fi

# Ensure we have created something
if [ "$ARCHIVE_CREATED" = false ]; then
    echo "❌ Failed to create archive"
    cd "$PROJECT_ROOT"
    exit 1
fi

cd "$PROJECT_ROOT"

echo "Release package created: $ARCHIVE_PATH"

# Get file size
if [[ "$OSTYPE" == "darwin"* ]]; then
    FILE_SIZE=$(stat -f%z "$ARCHIVE_PATH")
else
    FILE_SIZE=$(stat -c%s "$ARCHIVE_PATH")
fi

echo "Archive size: $FILE_SIZE bytes ($(($FILE_SIZE / 1024)) KB)"

# Create checksums
if command -v shasum &> /dev/null; then
    echo "Creating checksums..."
    cd "$BUILD_DIR"
    shasum -a 256 "$ARCHIVE_NAME" > "$ARCHIVE_NAME.sha256"
    echo "Checksum created: $ARCHIVE_NAME.sha256"
    cd "$PROJECT_ROOT"
elif command -v sha256sum &> /dev/null; then
    echo "Creating checksums..."
    cd "$BUILD_DIR"
    sha256sum "$ARCHIVE_NAME" > "$ARCHIVE_NAME.sha256"
    echo "Checksum created: $ARCHIVE_NAME.sha256"
    cd "$PROJECT_ROOT"
fi

# Test the archive integrity
echo "Testing archive integrity..."
cd "$BUILD_DIR"
if command -v unzip &> /dev/null && [[ "$ARCHIVE_NAME" == *.zip ]]; then
    if unzip -t "$ARCHIVE_NAME" > /dev/null 2>&1; then
        echo "✓ Archive integrity test passed"
    else
        echo "✗ Archive integrity test failed"
        cd "$PROJECT_ROOT"
        exit 1
    fi
elif command -v tar &> /dev/null && [[ "$ARCHIVE_NAME" == *.tar.gz ]]; then
    if tar -tzf "$ARCHIVE_NAME" > /dev/null 2>&1; then
        echo "✓ Archive integrity test passed"
    else
        echo "✗ Archive integrity test failed"
        cd "$PROJECT_ROOT"
        exit 1
    fi
fi
cd "$PROJECT_ROOT"

echo ""
echo "Files created:"
echo "  - $ARCHIVE_PATH"
if [ -f "$BUILD_DIR/$ARCHIVE_NAME.sha256" ]; then
    echo "  - $BUILD_DIR/$ARCHIVE_NAME.sha256"
fi

echo ""
echo "Archive contents:"
if [[ "$ARCHIVE_NAME" == *.zip ]]; then
    cd "$BUILD_DIR" && unzip -l "$ARCHIVE_NAME" | head -20 && cd "$PROJECT_ROOT"
else
    cd "$BUILD_DIR" && tar -tzf "$ARCHIVE_NAME" | head -20 && cd "$PROJECT_ROOT"
fi

echo ""
echo "Release Process Checklist:"
echo "□ 1. Test the plugin archive locally"
echo "□ 2. Commit all changes: git add . && git commit -m \"Release v$VERSION\""
echo "□ 3. Create and push tag: git tag v$VERSION && git push origin v$VERSION"
echo "□ 4. Create GitLab release:"
echo "     - Go to: https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/releases/new"
echo "     - Tag: v$VERSION"
echo "     - Title: v$VERSION"
echo "     - Upload: $ARCHIVE_PATH"
echo "□ 5. Test auto-update functionality"

echo ""
echo "GitLab Release Commands:"
echo "git add ."
echo "git commit -m \"Release v$VERSION\""
echo "git tag v$VERSION"
echo "git push origin main"
echo "git push origin v$VERSION"
