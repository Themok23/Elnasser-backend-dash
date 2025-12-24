#!/bin/bash
# Script to check and fix storage symlink

echo "=== Checking current storage symlink ==="
ls -la public/ | grep storage

echo ""
echo "=== Checking if symlink target exists ==="
if [ -L "public/storage" ]; then
    TARGET=$(readlink -f public/storage)
    echo "Symlink target: $TARGET"
    if [ -d "$TARGET" ]; then
        echo "✓ Target directory exists"
    else
        echo "✗ Target directory does NOT exist - symlink is broken!"
    fi
else
    echo "public/storage is not a symlink"
fi

echo ""
echo "=== Checking storage/app/public directory ==="
if [ -d "storage/app/public" ]; then
    echo "✓ storage/app/public exists"
    ls -la storage/app/public | head -5
else
    echo "✗ storage/app/public does NOT exist"
fi

