#!/bin/bash
# File: scripts/create-release.sh
# Script to create a release package for the Operaton DMN Evaluator plugin

set -e

# Configuration
PLUGIN_NAME="operaton-dmn-evaluator"
VERSION="$1"
BUILD_DIR="./build"
RELEASE_DIR="$BUILD_DIR/release"

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
cp -r assets/ "$RELEASE_DIR/$PLUGIN_NAME/" 2>/dev/null || echo "No assets directory found"
cp -r templates/ "$RELEASE_DIR/$PLUGIN_NAME/" 2>/dev/null || echo "No templates directory found"
cp -r includes/ "$RELEASE_DIR/$PLUGIN_NAME/" 2>/dev/null || echo "No includes directory found"
cp -r languages/ "$RELEASE_DIR/$PLUGIN_NAME/" 2>/dev/null || echo "No languages directory found"

# Copy main files
cp operaton-dmn-plugin.php "$RELEASE_DIR/$PLUGIN_NAME/"
cp README.md "$RELEASE_DIR/$PLUGIN_NAME/" 2>/dev/null || echo "No README.md found"
cp LICENSE "$RELEASE_DIR/$PLUGIN_NAME/" 2>/dev/null || echo "No LICENSE file found"

# Copy vendor directory if it exists (for update checker)
if [ -d "vendor/" ]; then
    echo "Including vendor directory..."
    cp -r vendor/ "$RELEASE_DIR/$PLUGIN_NAME/"
fi

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

# Create archive - try multiple methods
echo "Creating archive..."
cd "$RELEASE_DIR"

if command -v zip &> /dev/null; then
    # Use zip if available
    ARCHIVE_NAME="$PLUGIN_NAME-$VERSION.zip"
    echo "Using zip to create: $ARCHIVE_NAME"
    zip -r "../$ARCHIVE_NAME" "$PLUGIN_NAME/"
    ARCHIVE_PATH="$BUILD_DIR/$ARCHIVE_NAME"
elif command -v tar &> /dev/null; then
    # Fallback to tar
    ARCHIVE_NAME="$PLUGIN_NAME-$VERSION.tar.gz"
    echo "Using tar to create: $ARCHIVE_NAME"
    tar -czf "../$ARCHIVE_NAME" "$PLUGIN_NAME/"
    ARCHIVE_PATH="$BUILD_DIR/$ARCHIVE_NAME"
else
    echo "Neither zip nor tar command found!"
    echo "Archive NOT created, but files are ready in: $RELEASE_DIR/$PLUGIN_NAME/"
    echo ""
    echo "Please manually create an archive from: $RELEASE_DIR/$PLUGIN_NAME/"
    echo "  1. Navigate to: $RELEASE_DIR/"
    echo "  2. Right-click on '$PLUGIN_NAME' folder"
    echo "  3. Create compressed archive"
    echo "  4. Name it: $PLUGIN_NAME-$VERSION.zip"
    cd - > /dev/null
    exit 0
fi

cd - > /dev/null

echo "Release package created: $ARCHIVE_PATH"

# Create checksums if possible
if command -v shasum &> /dev/null; then
    echo "Creating checksums..."
    cd "$BUILD_DIR"
    shasum -a 256 "$ARCHIVE_NAME" > "$ARCHIVE_NAME.sha256"
    echo "Checksum created: $ARCHIVE_NAME.sha256"
elif command -v sha256sum &> /dev/null; then
    echo "Creating checksums..."
    cd "$BUILD_DIR"
    sha256sum "$ARCHIVE_NAME" > "$ARCHIVE_NAME.sha256"
    echo "Checksum created: $ARCHIVE_NAME.sha256"
fi

echo ""
echo "Files created:"
echo "  - $ARCHIVE_PATH"
if [ -f "$BUILD_DIR/$ARCHIVE_NAME.sha256" ]; then
    echo "  - $BUILD_DIR/$ARCHIVE_NAME.sha256"
fi

echo ""
echo "Next steps:"
echo "1. Test the plugin archive"
echo "2. Create a Git tag: git tag v$VERSION"
echo "3. Push the tag: git push origin v$VERSION"
echo "4. Create a release in your Git repository"
echo "5. Upload the archive file as a release asset"