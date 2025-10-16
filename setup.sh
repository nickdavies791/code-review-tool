#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Quode - One-Time Setup${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo -e "${YELLOW}Creating .env file...${NC}"
    if [ -f .env.example ]; then
        cp .env.example .env
        echo -e "${GREEN}✓ Created .env file${NC}"
        echo -e "${YELLOW}⚠ Please edit .env and add your API keys!${NC}"
        echo ""
    else
        echo -e "${RED}Error: .env.example not found${NC}"
        exit 1
    fi
fi

# Add quode.local to hosts file
echo -e "${YELLOW}Adding quode.local to /etc/hosts...${NC}"
echo "This requires sudo access (you'll be prompted for your password)"
echo ""

if grep -q "quode.local" /etc/hosts; then
    echo -e "${GREEN}✓ quode.local already in hosts file${NC}"
else
    if sudo sh -c 'echo "127.0.0.1 quode.local" >> /etc/hosts'; then
        echo -e "${GREEN}✓ Added quode.local to hosts file${NC}"
    else
        echo -e "${RED}✗ Failed to add quode.local to hosts file${NC}"
        echo "You can add it manually later with:"
        echo "  sudo sh -c 'echo \"127.0.0.1 quode.local\" >> /etc/hosts'"
    fi
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Setup Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Next steps:"
echo "1. Edit .env and add your API keys (if you haven't already)"
echo "2. Run: ./start.sh"
echo "3. Access at: http://quode.local:8080"
echo ""
