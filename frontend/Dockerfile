FROM node:20-alpine

WORKDIR /usr/src/app

COPY package.json package-lock.json* ./
RUN npm install --legacy-peer-deps || true

COPY . .

EXPOSE 3000

CMD ["npm", "start"]
