#!/bin/bash
# File: scripts/create-release.sh
# Script to create a release package for the Operaton DMN Evaluator plugin

set -e

# Configuration
PLUGIN_NAME="operaton-dmn-evaluator"
VERSION="$1"
BUILD_DIR="./build"
RELEASE_DIR="$BUILD_DIR/release"
ARCHIVE_NAME="$PLUGIN_NAME-$VERSION.zip"

# Check if version is provided
if [ -z "$VERSION" ]; then
    echo "Usage: $0 <version>"
    echo "Example: $0 1.0.0-beta.3"
    exit 1
fi

echo "Creating release package for version: $VERSION"

# Clean and create build directory
rm -rf "$BUILD_DIR"
mkdir -p "$RELEASE_DIR/$PLUGIN_NAME"

# Copy plugin files (exclude development files)
echo "Copying plugin files..."

# Include these files/directories
cp -r assets/ "$RELEASE_DIR/$PLUGIN_NAME/"
cp -r templates/ "$RELEASE_DIR/$PLUGIN_NAME/"
cp -r includes/ "$RELEASE_DIR/$PLUGIN_NAME/"
cp -r languages/ "$RELEASE_DIR/$PLUGIN_NAME/" 2>/dev/null || echo "No languages directory found"

# Copy main files
cp operaton-dmn-plugin.php "$RELEASE_DIR/$PLUGIN_NAME/"
cp README.md "$RELEASE_DIR/$PLUGIN_NAME/"
cp LICENSE "$RELEASE_DIR/$PLUGIN_NAME/" 2>/dev/null || echo "No LICENSE file found"

# Copy vendor directory if it exists (for update checker)
if [ -d "vendor/" ]; then
    echo "Including vendor directory..."
    cp -r vendor/ "$RELEASE_DIR/$PLUGIN_NAME/"
fi

# Update version in main plugin file
echo "Updating version in plugin file..."
sed -i.bak "s/Version: .*/Version: $VERSION/" "$RELEASE_DIR/$PLUGIN_NAME/operaton-dmn-plugin.php"
sed -i.bak "s/define('OPERATON_DMN_VERSION', '.*');/define('OPERATON_DMN_VERSION', '$VERSION');/" "$RELEASE_DIR/$PLUGIN_NAME/operaton-dmn-plugin.php"
rm "$RELEASE_DIR/$PLUGIN_NAME/operaton-dmn-plugin.php.bak"

# Create zip archive
echo "Creating archive: $ARCHIVE_NAME"
cd "$RELEASE_DIR"
zip -r "../$ARCHIVE_NAME" "$PLUGIN_NAME/"
cd - > /dev/null

echo "Release package created: $BUILD_DIR/$ARCHIVE_NAME"

# Create checksums
echo "Creating checksums..."
cd "$BUILD_DIR"
sha256sum "$ARCHIVE_NAME" > "$ARCHIVE_NAME.sha256"
md5sum "$ARCHIVE_NAME" > "$ARCHIVE_NAME.md5"

echo "Files created:"
echo "  - $BUILD_DIR/$ARCHIVE_NAME"
echo "  - $BUILD_DIR/$ARCHIVE_NAME.sha256"
echo "  - $BUILD_DIR/$ARCHIVE_NAME.md5"

echo ""
echo "Next steps:"
echo "1. Test the plugin archive"
echo "2. Create a Git tag: git tag v$VERSION"
echo "3. Push the tag: git push origin v$VERSION"
echo "4. Create a release in your Git repository"
echo "5. Upload the $ARCHIVE_NAME file as a release asset"