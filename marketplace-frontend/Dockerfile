FROM node:20-alpine

WORKDIR /app

# Copy only the dependencies
COPY package*.json ./
RUN npm install

# Don't copy the rest of the code, the volume will handle it
# COPY . .

# Start the dev server
CMD ["npm", "start"]
