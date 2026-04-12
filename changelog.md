# Changelog—MicroService Collection

## v1.1.0 - 12-04-2026

- Health check endpoint for article-service (`/health`)
- Rate limiting (sliding window) on API endpoints: global 100 req/min, upload 20 req/min, article creation 10 req/min
- Fix frontend vulnerability dependencies
- Use `RateLimiterFactoryInterface` instead of deprecated `RateLimiterFactory`

## v1.0.9 - 29-03-2026

- Fix npm audit vulnerabilities (peer dependencies update)
- Remove unnecessary comments and dead code cleanup

## v1.0.8 - 05-03-2026

- modify workflow dependencies

## v1.0.7 - 05-03-2026

- add kube-prometheus-stack for monitoring and alerting in Kubernetes cluster

## v1.0.6 - 10-02-2026

- Minikube implementation for local Kubernetes cluster setup

## v1.0.5 - 26-01-2026

- Grafana and Prometheus monitoring setup for microservices

## v1.0.4 - 12-01-2026

- Implementation of the Home Page, Creation Page and Article Detail Page
- Implementation of Unit and Integration Tests for Microservices Article

## v1.0.3 - 27-12-2025

- User synchronization and Kafka integration
- UserInfo entity and fixtures
- Article entity and API enhancements

## v1.0.2 - 24-12-2025

- Vite & Traefik migration
- Update CI workflows for new architecture
- Add article endpoints for microservice article

## v1.0.1 - 30-11-2025

- Improve security configurations for Dockerfile and secrets
- Workflow CI/CD for automated testing (eslint), sonarcloud analysis, and ZAP security scans

## v1.0.0 - 13-11-2025

- Configuration & test of microservice collection with Kong API Gateway
- Keycloak integration for authentication and login flow & header component