#!/bin/bash
# Test all supported PHP versions for fs-php-lite
# This script runs tests in Docker containers to verify compatibility

set -e

VERSIONS=("7.4" "8.0" "8.1" "8.2" "8.3")
FAILED_VERSIONS=()

echo "Testing fs-php-lite across PHP versions..."
echo "============================================"

for VERSION in "${VERSIONS[@]}"; do
    echo ""
    echo "Testing PHP $VERSION..."
    echo "------------------------"

    if docker run --rm \
        -v $(pwd):/app \
        -w /app \
        php:${VERSION}-cli \
        sh -c "
            apt-get update -qq && \
            apt-get install -y -qq git zip unzip > /dev/null && \
            curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer > /dev/null && \
            echo '✓ Composer installed' && \
            composer install -q && \
            echo '✓ Dependencies installed' && \
            php -v | head -1 && \
            vendor/bin/phpunit --version && \
            composer test
        "; then
        echo "✓ PHP $VERSION: PASSED"
    else
        echo "✗ PHP $VERSION: FAILED"
        FAILED_VERSIONS+=("$VERSION")
    fi
done

echo ""
echo "============================================"

if [ ${#FAILED_VERSIONS[@]} -eq 0 ]; then
    echo "✓ All PHP versions tested successfully!"
    exit 0
else
    echo "✗ Failed versions: ${FAILED_VERSIONS[*]}"
    exit 1
fi
