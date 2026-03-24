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
    compose build
    compose push
    ;;
  up)
    compose up -d --build --remove-orphans
    ;;
  deploy)
    compose pull
    compose up -d --remove-orphans
    ;;
  down)
    compose down
    ;;
  restart)
    compose up -d --build --force-recreate --remove-orphans
    ;;
  logs)
    compose logs -f --tail=200
    ;;
  ps)
    compose ps
    ;;
  *)
    echo "Usage: ./deploy.sh [build|push|release|up|deploy|down|restart|logs|ps] [tag]"
    exit 1
    ;;
esac
