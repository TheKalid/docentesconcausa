# Despliegue en Hostinger → kalid.tech

Este proyecto necesita **VPS** (no hosting compartido PHP): Next.js + PostgreSQL + Docker.

## Requisitos

- VPS Hostinger (Ubuntu 22/24 recomendado)
- Dominio `kalid.tech` apuntando al VPS
- Acceso SSH (`root` o usuario con `sudo`)

## 1. DNS en Hostinger

En **Dominios → kalid.tech → DNS / Zona DNS**:

| Tipo | Nombre | Valor        | TTL |
|------|--------|--------------|-----|
| A    | @      | IP del VPS   | 300 |
| A    | www    | IP del VPS   | 300 |

Espera unos minutos (hasta 1 h) a que propague.

## 2. Conectar por SSH e instalar Docker

```bash
ssh root@TU_IP_DEL_VPS
```

```bash
apt update && apt upgrade -y
apt install -y git curl ca-certificates

# Docker (script oficial)
curl -fsSL https://get.docker.com | sh
systemctl enable docker
systemctl start docker
```

## 3. Clonar el proyecto

```bash
mkdir -p /var/www && cd /var/www
git clone https://github.com/TheKalid/docentesconcausa.git
cd docentesconcausa
```

## 4. Variables de entorno

```bash
cp .env.example .env
nano .env
```

Ejemplo para producción:

```env
DATABASE_URL="postgresql://docentes:TU_PASSWORD_SEGURO@db:5432/docentesconcausa?schema=public"
POSTGRES_USER="docentes"
POSTGRES_PASSWORD="TU_PASSWORD_SEGURO"
POSTGRES_DB="docentesconcausa"

AUTH_SECRET="genera-con-openssl-rand-base64-32"
AUTH_URL="https://kalid.tech"
AUTH_TRUST_HOST="true"

OPENAI_API_KEY="sk-..."
```

Generar secreto:

```bash
openssl rand -base64 32
```

> **Importante:** usa contraseñas fuertes en `POSTGRES_PASSWORD` y `AUTH_SECRET`. No subas `.env` a Git.

## 5. Levantar la app (Docker)

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml --profile migrate run --rm migrate
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Comprueba que responde en el VPS:

```bash
curl -I http://127.0.0.1:3000
docker compose logs -f app
```

## 6. (Opcional) Importar datos legacy

Solo la primera vez, si quieres usuarios del dump MySQL:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec app node scripts/import-legacy-dump.mjs
```

## 7. HTTPS con Caddy (recomendado)

Caddy obtiene el certificado SSL automáticamente.

```bash
apt install -y debian-keyring debian-archive-keyring apt-transport-https
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
apt update && apt install -y caddy

cp /var/www/docentesconcausa/deploy/hostinger/Caddyfile /etc/caddy/Caddyfile
systemctl reload caddy
systemctl enable caddy
```

Abre en el navegador: **https://kalid.tech**

## 8. Actualizar después de un push

```bash
cd /var/www/docentesconcausa
git pull
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

## Firewall (recomendado)

```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

No expongas el puerto `5432` de PostgreSQL a internet; `docker-compose.prod.yml` ya lo oculta.

## Solución de problemas

| Problema | Qué revisar |
|----------|-------------|
| 502 en el dominio | `docker compose ps`, logs de `app`, Caddy apuntando a `127.0.0.1:3000` |
| Login no funciona | `AUTH_SECRET`, `AUTH_URL=https://kalid.tech`, `AUTH_TRUST_HOST=true` |
| Herramientas IA fallan | `OPENAI_API_KEY` en `.env` y reiniciar contenedor |
| BD vacía | `docker compose logs app` (migraciones) o ejecutar `db:import-legacy` |

## Alternativa sin Caddy (Nginx)

Si ya usas Nginx en Hostinger, proxy a `http://127.0.0.1:3000` y configura SSL con Certbot (`certbot --nginx -d kalid.tech -d www.kalid.tech`).
