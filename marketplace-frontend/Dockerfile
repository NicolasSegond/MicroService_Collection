FROM node:20-alpine

WORKDIR /app

# Copier uniquement les dépendances
COPY package*.json ./
RUN npm install

# Ne pas copier le reste du code, le volume fera le job
# COPY . .

# Lancer le serveur dev
CMD ["npm", "start"]
