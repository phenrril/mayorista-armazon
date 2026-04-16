# OpenClaw en Windows

## Objetivo

Usar un solo stack de OpenClaw para desarrollo y produccion, cambiando solo variables de entorno y configuracion local.

## Archivos del stack

- `docker/openclaw/docker-compose.yml`
- `docker/openclaw/.env.example`
- `docker/openclaw/openclaw.json.example`
- `docker/openclaw/volumes/workspace/AGENTS.md`
- `docker/openclaw/volumes/workspace/TOOLS.md`

## Preparacion inicial

1. Copiar `docker/openclaw/.env.example` a `docker/openclaw/.env`.
2. Completar:
   - `OPENCLAW_CONTAINER_NAME`
   - `OPENCLAW_GATEWAY_TOKEN`
   - `TELEGRAM_BOT_TOKEN`
   - `TELEGRAM_OWNER_ID`
   - `TELEGRAM_CLIENT_ID` si queres habilitar un segundo usuario por allowlist
   - `OPENAI_API_KEY` o `DEEPGRAM_API_KEY`
   - `MAYORISTA_API_BASE_URL`
   - `MAYORISTA_API_KEY`
3. Copiar `docker/openclaw/openclaw.json.example` a `docker/openclaw/volumes/config/openclaw.json`.
4. Ajustar valores segun entorno:
   - dev: API local y puertos locales
   - prod: URL real del sistema, nombre de contenedor propio y secretos finales

## Arranque

Desde `docker/openclaw`:

```bash
docker compose up -d
docker compose logs -f openclaw-gateway
```

Panel:

- `http://127.0.0.1:18789`

## Plugin local

El compose copia automaticamente el plugin `mayorista-api` desde `docker/openclaw/volumes/workspace/.openclaw/mayorista-api` a una ruta interna segura del contenedor antes de iniciar el gateway. Eso evita el bloqueo por permisos world-writable de los bind mounts de Windows.

## Dev y prod

El compose es el mismo para ambos entornos.

- En dev, usar `.env` apuntando a la API local, por ejemplo `http://host.docker.internal:8000/src/api/index.php`.
- En prod, usar otro `.env` con la URL real, otro `OPENCLAW_CONTAINER_NAME` y secretos productivos.
- Si queres mantener ambos archivos en la maquina, podes guardar por ejemplo `.env.dev` y `.env.prod` y copiar el que corresponda a `.env` antes de levantar.

## Reglas operativas

- Telegram queda restringido por `allowlist` con `TELEGRAM_OWNER_ID` y, si corresponde, `TELEGRAM_CLIENT_ID`.
- Las acciones mutables deben pedir confirmacion antes de ejecutar.
- El alta de cliente debe pedir todos los datos, aunque algunos opcionales puedan quedar vacios.
- No exponer el panel de OpenClaw directo a Internet.

## Backups

Respaldar de forma regular:

- `docker/openclaw/volumes/config`
- `docker/openclaw/volumes/workspace`
- base de datos del sistema PHP
- configuracion del stack PHP

## Checklist minima

- Telegram responde por DM
- audio llega transcripto
- consultas de lectura funcionan
- acciones mutables piden confirmacion
- luego de `si`, la API privada se invoca y responde
- logs sin errores criticos
