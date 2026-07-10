# Docentes con Causa

Migración del sitio legacy (PHP) [docentesconcausa.com](https://docentesconcausa.com/) a un stack moderno, escalable y contenerizado.

## 🧱 Stack

| Capa | Tecnología |
|------|------------|
| Frontend & Backend | Next.js 16 (App Router) |
| Lenguaje | TypeScript |
| Estilos | Tailwind CSS v4 |
| Base de datos | PostgreSQL |
| ORM | Prisma 6 |
| Autenticación | NextAuth (Auth.js v5) |
| Despliegue | Docker + docker-compose |

## 📁 Estructura

```
src/
├── app/
│   ├── page.tsx               # Landing (hero + herramientas + impacto)
│   ├── login/                 # Inicio de sesión
│   ├── registro/              # Registro de usuarios
│   ├── planes/                # Planes de suscripción (placeholder)
│   └── api/
│       ├── auth/[...nextauth] # Handler de NextAuth
│       ├── auth/register      # Alta de usuarios
│       └── chat               # Endpoint del asistente (placeholder IA)
├── components/                # Header, Hero, ToolCard, ChatWidget, ...
├── lib/
│   ├── prisma.ts              # Cliente Prisma (singleton)
│   ├── auth.ts                # Configuración NextAuth (credenciales)
│   └── tools.ts               # Catálogo de herramientas y navegación
└── types/                     # Tipos de NextAuth
prisma/
├── schema.prisma             # User, Account, Session, Plan, Subscription
└── migrations/               # Migración inicial
```

## 🚀 Desarrollo local

Requisitos: Node 20+, Docker.

1. Copia las variables de entorno:

```bash
cp .env.example .env
```

2. Genera un secreto para NextAuth y ponlo en `.env` (`AUTH_SECRET`):

```bash
openssl rand -base64 32
```

3. Levanta PostgreSQL (mapeado al puerto **5434** del host para evitar conflictos):

```bash
docker compose up -d db
```

4. Aplica las migraciones y genera el cliente:

```bash
npm install
npm run db:migrate
```

5. (Opcional) Importa el respaldo MySQL legacy a PostgreSQL:

```bash
npm run db:import-legacy
```

Lee `legacy/respaldo-db-2026-07-09.sql` e importa usuarios, grupos, historial de uso, recursos y convenios CETL. Las contraseñas PHP (`$2y$…`) siguen funcionando en el login.

6. Arranca el servidor de desarrollo:

```bash
npm run dev
```

App en http://localhost:3000

## 🐳 Despliegue con Docker

Levanta la app + base de datos con un solo comando (aplica migraciones automáticamente):

```bash
docker compose up --build
```

- App: http://localhost:3000
- PostgreSQL: `localhost:5434` (interno `db:5432`)

> Define `AUTH_SECRET`, `AUTH_URL` y `OPENAI_API_KEY` en tu entorno o en un `.env` antes de desplegar.

### Hostinger (kalid.tech)

Guía paso a paso: [`deploy/hostinger/README.md`](deploy/hostinger/README.md)

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

En producción la app escucha solo en `127.0.0.1:3000`; usa Caddy o Nginx con SSL para exponer `https://kalid.tech`.

## 📜 Scripts

| Script | Descripción |
|--------|-------------|
| `npm run dev` | Servidor de desarrollo |
| `npm run build` | Build de producción |
| `npm run start` | Servidor de producción |
| `npm run db:migrate` | Crea/aplica migraciones (dev) |
| `npm run db:deploy` | Aplica migraciones (producción) |
| `npm run db:studio` | Prisma Studio |
| `npm run db:import-legacy` | Importa dump MySQL legacy a PostgreSQL |

## 🔄 Estado de la migración

**Migrado desde el sitio legacy (solo a partir de lo visible en el sitio):**

- [x] Landing: header sticky, hero con efecto "matrix", grid de las 20 herramientas, impacto social, footer
- [x] Chat flotante (UI + endpoint placeholder)
- [x] Registro e inicio de sesión con NextAuth (credenciales) + persistencia en PostgreSQL
- [x] Modelo de datos base (usuarios, planes, suscripciones)
- [x] Contenerización (Docker + docker-compose)

**Pendiente (requiere el código PHP y/o la base de datos actual):**

- [ ] Lógica de cada herramienta IA (generadores, exámenes, simuladores, etc.)
- [ ] Catálogo de pagos y pasarela de suscripción
- [ ] Páginas de contenido: Misión, Creadores, Evidencias, Biblioteca, Tutorial
- [ ] Integración real del asistente de chat (IA)
- [x] Script de migración de datos legacy (`npm run db:import-legacy`)
- [ ] Recuperación de contraseña

## 🎨 Identidad visual

Colores tomados del sitio original:

- Primario: `#1e3a8a` · Primario claro: `#3b82f6`
- Acento: `#f39c12` · Acento hover: `#d35400`
- Fuente: Poppins
