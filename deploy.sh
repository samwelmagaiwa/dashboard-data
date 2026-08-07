#!/bin/sh
set -eu

COMMAND=${1:-up}
TAG=${2:-${IMAGE_TAG:-latest}}
COMPOSE_ENV_FILE=${COMPOSE_ENV_FILE:-compose.env}

if [ -f "$COMPOSE_ENV_FILE" ]; then
  COMPOSE_ARGS="--env-file $COMPOSE_ENV_FILE"
else
  COMPOSE_ARGS=""
fi

compose() {
  if [ -n "$COMPOSE_ARGS" ]; then
    docker compose --env-file "$COMPOSE_ENV_FILE" "$@"
  else
    docker compose "$@"
  fi
}

export IMAGE_TAG="$TAG"

case "$COMMAND" in
  build)
    compose build
    ;;
  push)
    compose push
    ;;
  release)
    # Build images from source then publish to Docker Hub.
    # Run from the dev machine or CI — requires source code and Docker login.
    # Usage: ./deploy.sh release [tag]   (default tag: latest)
    compose build
    compose push
    ;;
  pull)
    # Pull latest images from Docker Hub without restarting containers.
    compose pull
    ;;
  deploy)
    # Pull images from Docker Hub and (re)start all containers.
    # Run this on the production server after CI has pushed new images.
    # Usage: ./deploy.sh deploy [tag]   (default tag: latest)
    compose pull
    compose up -d --remove-orphans
    ;;
  up)
    # Build from local source and start (dev / first-time setup).
    compose up -d --build --remove-orphans
    ;;
  down)
    compose down
    ;;
  restart)
    # Force-recreate all containers without rebuilding images.
    compose up -d --force-recreate --remove-orphans
    ;;
  logs)
    compose logs -f --tail=200
    ;;
  ps|status)
    compose ps
    ;;
  *)
    echo "Usage: ./deploy.sh [build|push|release|pull|deploy|up|down|restart|logs|ps] [tag]"
    echo ""
    echo "  release [tag]  — build from source + push to Docker Hub  (dev / CI)"
    echo "  deploy  [tag]  — pull from Docker Hub + start containers  (production)"
    echo "  up             — build from local source + start          (dev / first-time)"
    echo "  restart        — force-recreate containers (no rebuild)"
    exit 1
    ;;
esac
