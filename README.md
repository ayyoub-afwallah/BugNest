# My Symfony Project

> **Note:** This README.md is automatically generated. To make changes, please edit the `README.template.md` file and then run the `./generate-readme.sh` script.

---

## 🏛️ System Architecture

The following diagram represents our Docker environment. It is generated directly from the `docker-compose.yml` file to ensure it is always up-to-date with our infrastructure.

```mermaid
graph TD
    subgraph "User Interaction"
        User/Browser
    end

    subgraph "Docker Environment (Appnet)"
        subgraph "Services"
            php["php"]
            nginx["nginx"]
            postgres["postgres"]
            pgadmin["pgadmin"]
            node["node"]
            minio["minio"]
        end

        subgraph "Persistent Data"
            db_data((db_data))
            minio_data((minio_data))
        end

    end

    php -- depends on --> db
    User/Browser -- "Port 8080" --> nginx
    nginx -- depends on --> php
    postgres -- stores data in --> db_data
    User/Browser -- "Port 5050" --> pgadmin
    User/Browser -- "Port 3000" --> node
    User/Browser -- "Port 9000" --> minio
    User/Browser -- "Port 9090" --> minio
    minio -- stores data in --> minio_data
```

---

## 🧪 Code Quality & Test Coverage

This summary shows the current state of our PHPUnit test coverage. The report is generated automatically after each test run.

```mermaid
%%{init: {'theme':'dark', 'themeVariables': { 'primaryColor': '#4CAF50', 'primaryTextColor': '#fff', 'primaryBorderColor': '#388E3C', 'lineColor': '#F57C00'}}}%%
xychart-beta
    title "Code Coverage by Directory"
    x-axis ["Application", "Domain", "Infrastructure", "Command", "Repository"]
    y-axis "Coverage %" 0 --> 100
    bar [100, 83.33, 4.35, 0, 0]
```

---

## 🚀 Getting Started

Follow these steps to get the project up and running on your local machine.

1.  **Clone the Repository**
    ```bash
    git clone <your-repository-url>
    cd <your-project-directory>
    ```

2.  **Build and Start Services**
    ```bash
    docker-compose up -d --build
    ```

3.  **Install Dependencies**
    ```bash
    docker-compose exec php composer install
    ```

4.  **Run Database Migrations**
    ```bash
    docker-compose exec php php bin/console doctrine:migrations:migrate
    ```

The application should now be available at `http://localhost:8080`.

## ⚙️ Available Commands

-   **Generate this README:**
    ```bash
    ./generate-readme.sh
    ```
-   **Run Tests:**
    ```bash
    docker-compose exec php php bin/phpunit
    ```
