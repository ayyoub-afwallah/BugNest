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