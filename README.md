# Quode - AI-Powered Code Review Tool

Intelligent code review with AI-powered insights and actionable recommendations.

## Features

- **AI-Powered Review**: Comprehensive code quality analysis with detailed, actionable feedback
- **Security Analysis**: Automatically detect security vulnerabilities and dangerous patterns
- **Performance Optimization**: Identify N+1 queries, missing indexes, and performance issues
- **Custom Guidelines**: Add your company's code review standards
- **Review History**: Save and revisit previous reviews
- **Interactive Chat**: Ask follow-up questions about the review

## Prerequisites

- Docker and Docker Compose
- GitHub Personal Access Token (for fetching repositories and PRs)
- Anthropic API Key or Google Gemini API Key (for AI reviews)

## Quick Start

### 1. Clone the Repository

```bash
git clone <repository-url>
cd code-review-tool
```

### 2. Run One-Time Setup

```bash
./setup.sh
```

This will:
- Create `.env` file from `.env.example`
- Add `quode.local` to your `/etc/hosts` file (requires sudo)
- Prompt you to add your API keys

Edit `.env` and add your API keys:

```
ANTHROPIC_API_KEY=your_anthropic_api_key_here
GEMINI_API_KEY=your_gemini_api_key_here
```

### 3. Start the Application

```bash
./start.sh
```

This will:
- Build the backend (PHP/Apache) and frontend (Vue.js/Nginx) containers
- Start all services
- Make the application available at http://quode.local:8080

### 4. Access the Application

Open your browser and navigate to:
```
http://quode.local:8080
```

## Usage

1. **Select a Repository**: Use the dropdown to search and select a GitHub repository
2. **Begin Review**: Click "Begin" to fetch open pull requests
3. **Select a PR**: Choose a pull request from the sidebar
4. **Generate Review**: Click "Review with AI" to get comprehensive analysis
5. **View Results**: See actionable items, security issues, and test scenarios
6. **Ask Questions**: Use the chat interface to get clarification on any review items

## Architecture

```
quode.local (nginx reverse proxy)
├── /          → Frontend (Vue.js + Vite)
└── /api/*     → Backend (PHP + Composer)
```

### Services

- **nginx**: Reverse proxy routing requests to frontend and backend
- **frontend**: Vue.js SPA served via Nginx
- **backend**: PHP REST API with Apache

## Development

### Stopping the Application

```bash
docker-compose down
```

### Viewing Logs

```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f backend
docker-compose logs -f frontend
docker-compose logs -f nginx
```

### Rebuilding After Changes

```bash
docker-compose up -d --build
```

### Accessing Containers

```bash
# Backend
docker exec -it quode-backend bash

# Frontend
docker exec -it quode-frontend sh

# Nginx
docker exec -it quode-nginx sh
```

## API Endpoints

- `GET /api/repos` - Fetch user's GitHub repositories
- `GET /api/prs?repo={owner/repo}` - Fetch pull requests for a repository
- `GET /api/pr-details?repo={owner/repo}&pr={number}` - Get PR details
- `POST /api/review` - Generate AI code review

## Configuration

### Backend Configuration

The backend is configured in `backend/index.php` and uses:
- PHP 8.2+
- Composer for dependencies
- Apache web server

### Frontend Configuration

The frontend is built with:
- Vue.js 3
- Vite for building
- Nginx for serving in production

### Custom Review Guidelines

You can add custom review guidelines through the Settings modal:
1. Click the gear icon in the top right
2. Navigate to "Review Guidelines"
3. Upload a file or paste your guidelines
4. Guidelines are stored in browser localStorage

## Troubleshooting

### Cannot Access quode.local

1. Verify the hosts file entry: `127.0.0.1 quode.local`
2. Check if containers are running: `docker-compose ps`
3. Check nginx logs: `docker-compose logs nginx`

### API Errors

1. Verify `.env` file has correct API keys
2. Check backend logs: `docker-compose logs backend`
3. Ensure GitHub token has proper permissions

### Build Failures

1. Clear Docker cache: `docker-compose down -v`
2. Rebuild: `docker-compose up -d --build --force-recreate`

## Team Sharing

To share this with your team:

1. Commit the changes (without `.env`):
   ```bash
   git add .
   git commit -m "Add Docker configuration"
   git push
   ```

2. Share these instructions:
   - Clone the repository
   - Copy `.env.example` to `.env` and add API keys
   - Add `quode.local` to hosts file
   - Run `docker-compose up -d`

## License

[Your License Here]

## Contributing

[Your Contributing Guidelines Here]
