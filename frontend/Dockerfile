FROM node:22-alpine

WORKDIR /app

COPY package*.json ./
RUN npm install --ignore-scripts

# Créer le répertoire de cache et s'assurer que l'utilisateur node a les bonnes permissions
RUN mkdir -p /app/node_modules/.cache && \
    chown -R node:node /app

USER node

CMD ["npm", "run", "dev"]