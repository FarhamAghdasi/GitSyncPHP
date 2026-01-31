# GitSyncPHP - GitHub Auto-Update Script for Shared Hosting

## Table of Contents

- [Description](#description)
- [Features](#features)
- [Project Structure](#project-structure)
- [Installation](#installation)
- [Usage](#usage)
- [Bundler Script](#bundler-script)
- [Configuration](#configuration)
- [Security](#security)
- [Backup Management](#backup-management)
- [Toast Notifications](#toast-notifications)
- [Troubleshooting](#troubleshooting)
- [License](#license)

## Description

This PHP script automatically updates your project directory from a GitHub repository. Designed for shared hosting environments without SSH access. The script is placed in a `/git` folder within your main project and keeps your application synchronized with your GitHub repository.

## Features

- 🚀 **Automatic updates** from GitHub
- 💾 **Automatic backup** before updating
- 📱 **Telegram notifications** after successful updates
- 🔒 **Security** with secret key and IP whitelist
- 🌐 **Web UI** for easy management
- 📜 **Complete logging** of all operations
- ⚡ **Lightweight** - minimal dependencies
- 🎨 **Component-based architecture** - CSS, JS, and HTML separated
- 🍞 **Toast notifications** - modern notification system

## Project Structure

```
GitSyncPHP/
├── git.php              (main file - uses external assets)
├── bundler.php          (generates single combined file)
├── .env.example
├── README.md
└── assets/
    ├── style.css        (all CSS styles)
    ├── header.php       (HTML head section)
    ├── footer.php       (modal dialogs and script include)
    └── script.js        (JavaScript with toast notifications)
```

## Installation

### Step 1: Copy Files to /git Folder

Copy the entire GitSyncPHP folder contents to your project's `/git` directory:

```
your-project/
├── /git/
│   ├── git.php
│   ├── .env.example
│   ├── bundler.php
│   └── /assets/
│       ├── style.css
│       ├── header.php
│       ├── footer.php
│       └── script.js
├── /your-app-files/
└── index.php
```

### Step 2: Configure .env File

```bash
# Navigate to git directory
cd your-project/git/

# Copy template file
cp .env.example .env

# Edit the configuration
nano .env
```

### Step 3: Security Setup

Create a secret key file for secure access:

```bash
# Generate a secure random key
echo "your-secure-random-key-here" > .update_key
```

## Usage

### Web Browser

Access the update panel:

```
https://yourdomain.com/your-project/git/git.php?key=your-secure-key
```

From the web interface you can:
- Check for updates
- View current version
- View commit history
- Run updates manually
- Manage backups (including "Delete All" button)
- View logs
- Change settings

### CLI Execution

```bash
# Navigate to git directory
cd /path/to/your-project/git/

# Run update
php git.php

# Check logs
cat update_log.txt
```

### Cron Job Automation

**Every hour:**
```bash
0 * * * * /usr/bin/php /path/to/your-project/git/git.php > /dev/null 2>&1
```

**Daily at 3 AM:**
```bash
0 3 * * * /usr/bin/php /path/to/your-project/git/git.php > /dev/null 2>&1
```

## Bundler Script

The `bundler.php` script generates a single combined file with all CSS and JS inlined. This is useful when you want a single file deployment.

### Generate Bundle

```bash
# Generate default bundle.php
php bundler.php

# Generate custom filename
php bundler.php my-bundle.php
```

The bundler will:
1. Read all assets from the `assets/` folder
2. Combine them into a single PHP file
3. Include all CSS in `<style>` tags
4. Include all JavaScript in `<script>` tags

## Configuration

### .env Settings

```env
# ==========================================
# GitHub Repository Configuration
# ==========================================
GITHUB_TOKEN=ghp_your_github_token_here
REPO_USER=your-github-username
REPO_NAME=your-repository-name
BRANCH=main

# ==========================================
# Telegram Notifications (Optional)
# ==========================================
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_CHAT_ID=-1001234567890

# ==========================================
# Backup Settings
# ==========================================
BACKUP_BEFORE_UPDATE=true
BACKUP_DIR=__backups
DELETE_EXTRACTED_FILES=true

# ==========================================
# Files & Paths
# ==========================================
LOG_FILE=update_log.txt
VERSION_FILE=.version
EXCLUDE_FILES=git,.env,.update_key,.ip_whitelist,__backups,*.log,update_log.txt
```

## Security

### Secret Key Method

Create a `.update_key` file with a random secure string:

```bash
# Generate a random key
openssl rand -base64 32 > .update_key

# Or manually create
echo "complex-random-string-at-least-32-chars" > .update_key
```

**Access with key:**
```
https://yourdomain.com/path/to/git/git.php?key=your-secure-key
```

### IP Whitelist Method

Create an `.ip_whitelist` file:

```bash
nano .ip_whitelist
```

Add IP addresses (one per line):
```
192.168.1.100
10.0.0.5
203.0.113.50
```

**Note:** You can use both methods together for maximum security.

## Backup Management

Backups are automatically created in the `__backups/` directory before each update.

**Backup Features:**
- Automatic creation before updates
- ZIP format compression
- Timestamped filenames
- Secure with .htaccess protection
- **Delete All** button to remove all backups at once

## Toast Notifications

All browser alerts have been replaced with modern toast notifications:

- ✅ Success notifications (green)
- ❌ Error notifications (red)
- ⚠️ Warning notifications (orange)
- ℹ️ Info notifications (blue)

Toast features:
- Animated slide-in and fade-out
- Auto-dismiss after 4 seconds
- Manual close button
- Click to dismiss

## Troubleshooting

### 401 Unauthorized

**Cause:** Invalid or expired GitHub token

**Solution:**
```bash
# Check token validity
curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/user
```

### 403 Forbidden

**Cause:** Rate limit exceeded or insufficient permissions

**Solution:**
```bash
# Check rate limit
curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/rate_limit
```

### 404 Not Found

**Cause:** Repository doesn't exist or wrong name

**Solution:** Verify `REPO_USER` and `REPO_NAME` in .env file

### ZipArchive Not Available

**Solution:**
```bash
# Ubuntu/Debian
sudo apt-get install php-zip
sudo service apache2 restart

# CentOS/RHEL
sudo yum install php-pecl-zip
sudo service httpd restart
```

### cURL Not Available

**Solution:**
```bash
# Ubuntu/Debian
sudo apt-get install php-curl
sudo service apache2 restart

# CentOS/RHEL
sudo yum install php-curl
sudo service httpd restart
```

## License

This project is released under the MIT License. See the [LICENSE](LICENSE) file for details.

---

**Made with ❤️ for the PHP Community**
