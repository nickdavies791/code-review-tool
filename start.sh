#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Quode - AI Code Review Tool${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo -e "${RED}Error: .env file not found!${NC}"
    echo -e "${YELLOW}Please copy .env.example to .env and add your API keys:${NC}"
    echo "  cp .env.example .env"
    echo ""
    exit 1
fi

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}Error: Docker is not running!${NC}"
    echo "Please start Docker Desktop and try again."
    echo ""
    exit 1
fi

# Check for quode.local in hosts file
if ! grep -q "quode.local" /etc/hosts; then
    echo -e "${YELLOW}⚠ quode.local not found in /etc/hosts${NC}"
    echo "Run the setup script first: ./setup.sh"
    echo ""
fi

# Start containers
echo -e "${GREEN}Starting Quode...${NC}"
echo ""

docker-compose up -d

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  Quode is now running!${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo ""
    echo "Access the application at:"
    echo -e "  ${GREEN}http://quode.local:8080${NC}"
    echo ""
    echo "Useful commands:"
    echo "  View logs:    docker-compose logs -f"
    echo "  Stop:         docker-compose down"
    echo "  Rebuild:      docker-compose up -d --build"
    echo ""
else
    echo ""
    echo -e "${RED}Failed to start Quode. Check the error messages above.${NC}"
    echo ""
    exit 1
fi
