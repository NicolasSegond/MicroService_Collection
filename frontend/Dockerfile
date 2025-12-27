#-----------------------------------------------
# Base - Image commune Node
#-----------------------------------------------
FROM node:22-alpine AS base

WORKDIR /app

RUN apk add --no-cache dos2unix

COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN dos2unix /usr/local/bin/docker-entrypoint && \
    chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]

#-----------------------------------------------
# Dev - node_modules montés depuis l'host
#-----------------------------------------------
FROM base AS dev

ENV NODE_ENV=dev

RUN chown -R 1000:1000 /app

USER 1000:1000

CMD ["npm", "run", "dev"]

#-----------------------------------------------
# Prod - Build optimisé, servi par vite preview
#-----------------------------------------------
FROM base AS prod

ENV NODE_ENV=prod

COPY package*.json ./
RUN npm ci --ignore-scripts

COPY . .
RUN npm run build

EXPOSE 3000

USER node

CMD ["npm", "run", "preview", "--", "--host", "0.0.0.0", "--port", "3000"]
