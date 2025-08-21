# Use official Node.js Alpine image
FROM node:20-alpine

# Set working directory
WORKDIR /usr/src/app

# Install dependencies
COPY package.json package-lock.json* ./
# Add build tools for native dependencies (optional but helpful)
RUN apk add --no-cache python3 make g++ \
  && npm install --legacy-peer-deps

# Copy the rest of the app
COPY . .

# Expose the development port
EXPOSE 3000

# Ensure react-scripts is available in PATH
ENV PATH="./node_modules/.bin:$PATH"

# Start the app
CMD ["npm", "start"]