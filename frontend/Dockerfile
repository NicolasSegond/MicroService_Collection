FROM node:22-alpine

WORKDIR /app

COPY package*.json ./

RUN mkdir -p /app/node_modules/.cache && \
    chown -R node:node /app

USER node

CMD ["sh", "-c", "npm install && npm run dev"]
