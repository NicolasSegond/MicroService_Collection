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
# Prod - Build optimisé, servi par nginx
#-----------------------------------------------
FROM base AS build

ENV NODE_ENV=production

COPY package*.json ./
RUN npm ci --ignore-scripts

COPY . .
RUN npm run build

#-----------------------------------------------
# Prod - Nginx pour servir les fichiers statiques
#-----------------------------------------------
FROM nginx:alpine AS prod

# Copier la config nginx
RUN echo 'server { \
    listen 3000; \
    root /usr/share/nginx/html; \
    index index.html; \
    location / { \
        try_files $uri $uri/ /index.html; \
    } \
}' > /etc/nginx/conf.d/default.conf

# Copier les fichiers buildés
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 3000

CMD ["nginx", "-g", "daemon off;"]
