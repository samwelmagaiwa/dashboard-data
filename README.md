# Health Dashboard & Data Sync System

A modern, high-performance medical dashboard system designed for real-time monitoring of clinical visits, diagnostic distributions, and doctor performance. Featuring a robust data synchronization pipeline and advanced ICD-10 reporting.

## 🚀 Project Overview

This repository contains two primary components:
- **Backend (`dashboard-backend/`)**: A Laravel 12 API serving as the data hub, featuring automated sync jobs and statistical aggregation.
- **Frontend (`dashboard-frontend/`)**: A Vue 3 dashboard built with Vite and CoreUI, providing premium data visualizations and detailed clinical reports.

## 🛠️ Tech Stack

- **Core**: PHP 8.2+, Laravel 12, Node.js, Vue 3.
- **Styling**: Vanilla CSS, CoreUI Components.
- **Data**: MariaDB/MySQL, Redis (caching).
- **Environment**: Docker, Docker Compose.

## ✨ Key Features

- **Automated Data Sync**: Fetches and upserts visit data from external APIs into a local high-speed database.
- **Intelligent Statistics**: Aggregated daily and range-based stats for clinics, departments, and doctors.
- **ICD-10 Integration**: Advanced reporting with standard clinical abbreviations (e.g., MI, HTN) and expandable medical descriptions.
- **Data Quality Tools**: Built-in gap analysis and repair tools to ensure data consistency between local and external systems.
- **Zero-Scroll Reporting**: Premium reporting interface optimized for density and readability.

## 📦 Getting Started

### Backend Setup
```bash
cd dashboard-backend
composer install
composer run setup
php artisan serve
```

### Frontend Setup
```bash
cd dashboard-frontend
npm install
npm run dev
```

### Docker Deployment
```bash
docker-compose up -d
```

## 🚢 Deployment

The project includes a unified deployment script for CI/CD:
- Use `./deploy.sh release` to push to Docker Hub.
- Use `./deploy.sh deploy` on the target server to pull and restart the stack.

---
© 2026 Dashboard Data System. All rights reserved.
