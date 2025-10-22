#!/bin/bash

# Rebuild frontend container with latest code changes
echo "🔨 Rebuilding frontend container..."
docker-compose build --no-cache frontend

echo "🔄 Restarting frontend container..."
docker-compose up -d frontend

echo "🔄 Restarting nginx..."
docker-compose restart nginx

echo "✅ Frontend rebuilt and deployed!"
echo "🌐 Visit http://quode.local:8080 to see your changes"
