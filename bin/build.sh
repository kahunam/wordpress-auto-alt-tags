#!/bin/bash
#
# WordPress Auto Alt Tags - Build Script
# Creates a distribution package and validates WordPress plugin requirements
#
# Usage: ./bin/build.sh [--skip-checks] [--version X.Y.Z]
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_SLUG="auto-alt-tags"
PLUGIN_FILE="auto-alt-tags.php"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
DIST_DIR="$ROOT_DIR/dist"
BUILD_DIR="$DIST_DIR/$PLUGIN_SLUG"

# Parse arguments
SKIP_CHECKS=false
VERSION=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-checks)
            SKIP_CHECKS=true
            shift
            ;;
        --version)
            VERSION="$2"
            shift 2
            ;;
        -h|--help)
            echo "Usage: $0 [--skip-checks] [--version X.Y.Z]"
            echo ""
            echo "Options:"
            echo "  --skip-checks    Skip WordPress requirement validation"
            echo "  --version X.Y.Z  Override version number"
            echo "  -h, --help       Show this help message"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

# Functions
print_header() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Extract version from plugin file if not provided
get_version() {
    if [ -z "$VERSION" ]; then
        VERSION=$(grep -m1 "Version:" "$ROOT_DIR/$PLUGIN_FILE" | sed 's/.*Version: *//' | tr -d '[:space:]')
    fi
    echo "$VERSION"
}

# Main build process
main() {
    print_header "WordPress Auto Alt Tags - Build Script"

    cd "$ROOT_DIR"

    VERSION=$(get_version)
    echo -e "Building version: ${GREEN}$VERSION${NC}"
    echo ""

    # Step 1: Run WordPress requirement checks
    if [ "$SKIP_CHECKS" = false ]; then
        print_header "Running WordPress Plugin Checks"

        if php "$SCRIPT_DIR/wp-plugin-check.php"; then
            print_success "All WordPress plugin checks passed"
        else
            print_error "WordPress plugin checks failed"
            echo ""
            echo "Run with --skip-checks to build anyway (not recommended)"
            exit 1
        fi
    else
        print_warning "Skipping WordPress requirement checks"
    fi

    # Step 2: Clean previous build
    print_header "Preparing Build Directory"

    if [ -d "$DIST_DIR" ]; then
        rm -rf "$DIST_DIR"
        print_success "Cleaned previous build"
    fi

    mkdir -p "$BUILD_DIR"
    print_success "Created build directory: $BUILD_DIR"

    # Step 3: Copy plugin files
    print_header "Copying Plugin Files"

    # Main plugin file
    cp "$PLUGIN_FILE" "$BUILD_DIR/"
    print_success "Copied $PLUGIN_FILE"

    # Includes directory
    if [ -d "includes" ]; then
        cp -r includes "$BUILD_DIR/"
        print_success "Copied includes/"
    fi

    # Assets directory
    if [ -d "assets" ]; then
        cp -r assets "$BUILD_DIR/"
        print_success "Copied assets/"
    fi

    # Languages directory
    if [ -d "languages" ]; then
        cp -r languages "$BUILD_DIR/"
        print_success "Copied languages/"
    fi

    # Documentation files (required for WordPress.org)
    cp readme.txt "$BUILD_DIR/"
    print_success "Copied readme.txt"

    if [ -f "LICENSE" ]; then
        cp LICENSE "$BUILD_DIR/"
        print_success "Copied LICENSE"
    fi

    # Step 4: Create index.php files for security
    print_header "Adding Security Files"

    # Create index.php in all directories to prevent directory listing
    find "$BUILD_DIR" -type d -exec sh -c '
        if [ ! -f "$1/index.php" ]; then
            echo "<?php\n// Silence is golden." > "$1/index.php"
        fi
    ' _ {} \;
    print_success "Added index.php to all directories"

    # Step 5: Create zip file
    print_header "Creating Distribution Package"

    cd "$DIST_DIR"
    ZIP_FILE="${PLUGIN_SLUG}-${VERSION}.zip"

    zip -r "$ZIP_FILE" "$PLUGIN_SLUG" -x "*.DS_Store" -x "*__MACOSX*"
    print_success "Created $ZIP_FILE"

    # Step 6: Calculate checksums
    if command -v sha256sum &> /dev/null; then
        sha256sum "$ZIP_FILE" > "${ZIP_FILE}.sha256"
        print_success "Created SHA256 checksum"
    elif command -v shasum &> /dev/null; then
        shasum -a 256 "$ZIP_FILE" > "${ZIP_FILE}.sha256"
        print_success "Created SHA256 checksum"
    fi

    # Step 7: Summary
    print_header "Build Complete"

    echo "Distribution files:"
    echo ""
    ls -lh "$DIST_DIR"/*.zip 2>/dev/null || true
    ls -lh "$DIST_DIR"/*.sha256 2>/dev/null || true
    echo ""

    ZIP_SIZE=$(du -h "$DIST_DIR/$ZIP_FILE" | cut -f1)
    FILE_COUNT=$(find "$BUILD_DIR" -type f | wc -l | tr -d ' ')

    echo -e "Package: ${GREEN}$ZIP_FILE${NC}"
    echo -e "Size: ${GREEN}$ZIP_SIZE${NC}"
    echo -e "Files: ${GREEN}$FILE_COUNT${NC}"
    echo ""
    echo -e "${GREEN}Build successful!${NC}"
    echo ""
    echo "To install:"
    echo "  1. Go to WordPress Admin > Plugins > Add New > Upload Plugin"
    echo "  2. Choose: $DIST_DIR/$ZIP_FILE"
    echo "  3. Click Install Now"
}

# Run main function
main
