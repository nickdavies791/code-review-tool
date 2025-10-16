# Quode Quick Start Guide

## First Time Setup (2 minutes)

### 1. Run Setup Script

```bash
chmod +x setup.sh
./setup.sh
```

This will:
- Create your `.env` file
- Add `quode.local` to your hosts file (you'll be prompted for sudo password)

### 2. Add Your API Keys

You'll need at least one of these:
- **Anthropic API Key**: Get from https://console.anthropic.com/
- **Google Gemini API Key**: Get from https://makersuite.google.com/app/apikey

Edit `.env` and add your key(s):
```bash
nano .env
```

### 3. Start Quode

```bash
chmod +x start.sh
./start.sh
```

### 4. Open in Browser

Navigate to: **http://quode.local:8080**

---

## Daily Usage

### Start
```bash
./start.sh
# or
docker-compose up -d
```

### Stop
```bash
docker-compose down
```

### View Logs
```bash
docker-compose logs -f
```

### Rebuild (after updates)
```bash
docker-compose up -d --build
```

---

## Troubleshooting

### "Cannot connect to quode.local"
- Check if containers are running: `docker-compose ps`
- Verify hosts file: `cat /etc/hosts | grep quode`
- Try: `docker-compose restart nginx`

### "API error" or "Failed to fetch"
- Check your `.env` file has valid API keys
- View backend logs: `docker-compose logs backend`

### "Port 80 already in use"
- Stop other services using port 80
- Or change the port in `docker-compose.yml` (e.g., `8080:80`)

### Docker issues
```bash
# Full restart
docker-compose down -v
docker-compose up -d --build --force-recreate

# Clear everything and start fresh
docker system prune -a
```

---

## Tips

- Use the **Settings** (⚙️ icon) to add favorite repos and custom guidelines
- View **History** (🕐 icon) to revisit previous reviews
- Reviews are saved in your browser's localStorage
- The app requires a GitHub token with repo access for private repos

---

## Support

- Check logs: `docker-compose logs -f [service]`
- Restart services: `docker-compose restart [service]`
- Full README: See `README.md`
