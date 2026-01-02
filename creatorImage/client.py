#!/usr/bin/env python3
"""
Скрипт для сборки клиентского Docker образа Laravel
"""

import os
import shutil
import json
import subprocess
import argparse
from pathlib import Path
from datetime import datetime
import yaml

class LaravelClientBuilder:
    def __init__(self, project_name, output_dir="build_client"):
        self.project_name = project_name
        self.output_dir = Path(output_dir)
        self.client_files_dir = Path("client_files")
        self.laravel_dist_dir = Path("laravel_project")
        
    def validate_structure(self):
        """Проверка необходимой структуры директорий"""
        required_dirs = [self.client_files_dir, self.laravel_dist_dir]
        
        for dir_path in required_dirs:
            if not dir_path.exists():
                print(f"❌ Отсутствует директория: {dir_path}")
                print("Создайте структуру:")
                print(f"  {dir_path}/ - файлы клиента (контроллеры, миграции и т.д.)")
                print(f"  {dir_path}/app/Http/Controllers/ - контроллеры")
                print(f"  {dir_path}/database/migrations/ - миграции")
                print(f"  {dir_path}/routes/ - маршруты")
                return False
        return True
    
    def prepare_build_directory(self):
        """Подготовка директории для сборки"""
        print(f"📁 Подготовка директории сборки: {self.output_dir}")
        
        if self.output_dir.exists():
            shutil.rmtree(self.output_dir)
        
        self.output_dir.mkdir(parents=True, exist_ok=True)
        
        # Создаем поддиректории
        (self.output_dir / "dockerfiles").mkdir(exist_ok=True)
        (self.output_dir / "configs" / "init").mkdir(parents=True, exist_ok=True)
    
    def copy_client_files(self):
        """Копирование клиентских файлов"""
        print(f"📋 Копирование клиентских файлов из {self.client_files_dir}")
        
        dest_dir = self.output_dir / "client_files"
        if dest_dir.exists():
            shutil.rmtree(dest_dir)
        
        shutil.copytree(self.client_files_dir, dest_dir)
    
    def copy_laravel_dist(self):
        """Копирование базового Laravel проекта"""
        print(f"📋 Копирование Laravel проекта из {self.laravel_dist_dir}")
        
        dest_dir = self.output_dir / "laravel_project"
        if dest_dir.exists():
            shutil.rmtree(dest_dir)
        
        shutil.copytree(self.laravel_dist_dir, dest_dir)
    
    def create_dockerfile(self):
        """Создание Dockerfile для клиента"""
        print("🐳 Создание Dockerfile клиента...")
        
        dockerfile_path = self.output_dir / "dockerfiles" / "Dockerfile_client"
        
        # Читаем шаблон Dockerfile
        dockerfile_content = Path("dockerfiles/Dockerfile_client").read_text()
        
        # Записываем в выходную директорию
        dockerfile_path.write_text(dockerfile_content)
    
    def create_init_script(self):
        """Создание скрипта инициализации"""
        print("📜 Создание скриптов инициализации...")
        
        # Копируем существующий start-php.sh
        shutil.copy(
            "docker/configs/init/start-php.sh",
            self.output_dir / "configs" / "init" / "start-php.sh"
        )
    
    def create_docker_compose(self, config):
        """Создание docker-compose.yml для клиента"""
        print("📝 Создание docker-compose.yml...")
        
        compose_template = f"""
version: '3.8'

networks:
  {self.project_name}servernet:
    driver: bridge
    ipam:
      config:
        - subnet: 172.20.0.0/16
          gateway: 172.20.0.1

volumes:
  {self.project_name}-data:
  {self.project_name}-logs:

services:
  ### CLIENT PHP ###
  {self.project_name}-php:
    build:
      context: .
      dockerfile: dockerfiles/Dockerfile_client
    container_name: {self.project_name}-php
    restart: unless-stopped
    volumes:
      - {self.project_name}-data:/var/www/html:rw
      - {self.project_name}-logs:/var/log/php:rw
    environment:
      - APP_ENV={config.get('APP_ENV', 'production')}
      - APP_DEBUG={config.get('APP_DEBUG', 'false')}
      - APP_URL={config.get('APP_URL', 'http://localhost')}
      
      # Коннект к БД
      - DB_CONNECTION={config.get('DB_CONNECTION', 'mysql')}
      - DB_HOST={config.get('DB_HOST', 'db')}
      - DB_PORT={config.get('DB_PORT', '3306')}
      - DB_DATABASE={config.get('DB_DATABASE', self.project_name)}
      - DB_USERNAME={config.get('DB_USERNAME', 'root')}
      - DB_PASSWORD={config.get('DB_PASSWORD', '')}
      
      # Режим инициализации
      - INIT_MODE=client
    networks:
      {self.project_name}servernet:
        ipv4_address: 172.20.0.13
    command: ["start-php.sh"]
    
  ### MARIADB ###
  {self.project_name}-db:
    image: mariadb:10.8
    container_name: {self.project_name}-db
    environment:
      MARIADB_ROOT_PASSWORD: {config.get('DB_PASSWORD', 'root')}
      MARIADB_DATABASE: {config.get('DB_DATABASE', self.project_name)}
      MARIADB_USER: {config.get('DB_USERNAME', 'root')}
      MARIADB_PASSWORD: {config.get('DB_PASSWORD', 'root')}
    volumes:
      - {self.project_name}-db-data:/var/lib/mysql
    networks:
      {self.project_name}servernet:
        ipv4_address: 172.20.0.14
    restart: unless-stopped
    
  ### NGINX ###
  {self.project_name}-nginx:
    build:
      context: .
      dockerfile: dockerfiles/Dockerfile_nginx
    container_name: {self.project_name}-nginx
    ports:
      - "{config.get('HTTP_PORT', '8080')}:80"
      - "{config.get('HTTPS_PORT', '8443')}:443"
    volumes:
      - {self.project_name}-data:/var/www/html:ro
      - {self.project_name}-logs:/var/log/nginx:rw
    networks:
      {self.project_name}servernet:
        ipv4_address: 172.20.0.12
    depends_on:
      - {self.project_name}-php
    restart: unless-stopped

volumes:
  {self.project_name}-data:
  {self.project_name}-db-data:
  {self.project_name}-logs:
"""
        
        compose_path = self.output_dir / "docker-compose.yml"
        compose_path.write_text(compose_template)
    
    def create_env_file(self, config):
        """Создание .env файла"""
        print("⚙️  Создание .env файла...")
        
        env_content = f"""# Application
APP_NAME="{self.project_name}"
APP_ENV={config.get('APP_ENV', 'production')}
APP_DEBUG={config.get('APP_DEBUG', 'false')}
APP_URL={config.get('APP_URL', 'http://localhost')}
APP_KEY=base64:{os.urandom(32).hex()}

# Database
DB_CONNECTION={config.get('DB_CONNECTION', 'mysql')}
DB_HOST={config.get('DB_HOST', 'db')}
DB_PORT={config.get('DB_PORT', '3306')}
DB_DATABASE={config.get('DB_DATABASE', self.project_name)}
DB_USERNAME={config.get('DB_USERNAME', 'root')}
DB_PASSWORD={config.get('DB_PASSWORD', '')}

# Redis
REDIS_HOST={config.get('REDIS_HOST', 'redis')}
REDIS_PASSWORD=null
REDIS_PORT=6379

# Session
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Cache
CACHE_DRIVER=file
QUEUE_CONNECTION=database
"""
        
        env_path = self.output_dir / ".env"
        env_path.write_text(env_content)
    
    def create_build_script(self):
        """Создание скрипта для сборки"""
        print("🔧 Создание скрипта сборки...")
        
        build_script = f"""#!/bin/bash
# Скрипт сборки клиентского образа {self.project_name}

set -e

echo "🚀 Начало сборки клиентского образа {self.project_name}..."

# Переменные
IMAGE_NAME="{self.project_name}-client"
VERSION="$(date +%Y%m%d_%H%M%S)"
TAG="${{IMAGE_NAME}}:${{VERSION}}"
TAG_LATEST="${{IMAGE_NAME}}:latest"

# Сборка Docker образа
echo "🐳 Сборка Docker образа..."
docker build -t "${{TAG}}" -t "${{TAG_LATEST}}" \\
    -f dockerfiles/Dockerfile_client .

echo ""
echo "✅ Сборка завершена!"
echo ""
echo "📦 Образы:"
echo "   - ${{TAG}}"
echo "   - ${{TAG_LATEST}}"
echo ""
echo "🚀 Запуск проекта:"
echo "   docker-compose up -d"
echo ""
echo "📊 Проверка статуса:"
echo "   docker-compose ps"
echo ""
echo "📝 Логи:"
echo "   docker-compose logs -f"
"""
        
        build_path = self.output_dir / "build.sh"
        build_path.write_text(build_script)
        build_path.chmod(0o755)
    
    def create_readme(self):
        """Создание README файла"""
        print("📖 Создание README...")
        
        readme_content = f"""# Клиентский проект: {self.project_name}

## Структура проекта
