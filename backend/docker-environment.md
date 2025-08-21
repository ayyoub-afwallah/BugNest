# Docker Environment Diagram

Generated on: 2025-08-21 17:01:43
Diagram type: Network

## Environment Overview

- **Services**: 6
- **Volumes**: 2
- **Networks**: 1

## Services

### 🐘 php
- **Image**: `custom build`

### 🌐 nginx
- **Image**: `nginx:1.25`
- **Ports**: 8080

### 🐘 db
- **Image**: `postgres:15`

### 🐳 pgadmin
- **Image**: `dpage/pgadmin4`
- **Ports**: 5050

### 💚 node
- **Image**: `custom build`
- **Ports**: 3000

### 🐳 symfony_minio
- **Image**: `quay.io/minio/minio`
- **Ports**: 9000, 9090

## network Diagram

```mermaid
graph LR

    %% Docker Network Topology

    Internet([🌐 Internet])

    service_0["🐘 php"]
    service_1["🌐 nginx"]
    Internet -->|":8080"| service_1
    service_2["🐘 db"]
    service_3["🐳 pgadmin"]
    Internet -->|":5050"| service_3
    service_4["💚 node"]
    Internet -->|":3000"| service_4
    service_5["🐳 symfony_minio"]
    Internet -->|":9000,9090"| service_5
    service_2 -.-> service_0
    service_0 -.-> service_1
```