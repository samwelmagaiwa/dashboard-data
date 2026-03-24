# Step 1: Build
FROM node:20-alpine AS builder
ARG VITE_API_BASE_URL=/api/v1
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Step 2: Serve
FROM nginx:stable-alpine
COPY --from=builder /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/templates/default.conf.template
ENV FRONTEND_SERVER_NAME=clinicaldashboard.mloganzila.or.tz
ENV BACKEND_UPSTREAM=http://dashboard_backend:80
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
