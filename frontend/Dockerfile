FROM node:22-alpine
WORKDIR /app

# Installer les dépendances (cache layer)
COPY package*.json ./
RUN npm install

# Copier le code
COPY . .

# Lancer Vite en mode dev
CMD ["npm", "run", "dev"]