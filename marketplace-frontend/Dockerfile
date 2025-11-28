FROM node:20-alpine

WORKDIR /app

COPY package*.json ./
RUN npm install

# Créer le répertoire de cache et s'assurer que l'utilisateur node a les bonnes permissions
RUN mkdir -p /app/node_modules/.cache && \
    chown -R node:node /app

USER node

CMD ["npm", "start"]