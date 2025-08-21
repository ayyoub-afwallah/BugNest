graph TD
    subgraph "User Interaction"
        User/Browser
    end

    subgraph "Docker Environment (Appnet)"
        subgraph "Services"
            php["php (app_php)"]
            nginx["nginx (app_nginx)"]
            db["db (bug_db)"]
            pgadmin["pgadmin (pgadmin)"]
            node["node (app_front_node)"]
            symfony_minio["symfony_minio (symfony_minio)"]
        end

        subgraph "Persistent Data"
            db_data((db_data))
            minio_data((minio_data))
        end

    end

    php -- depends on --> db
    User/Browser -- "Port 8080" --> nginx
    nginx -- depends on --> php
    db -- stores data in --> db_data
    User/Browser -- "Port 5050" --> pgadmin
    User/Browser -- "Port 3000" --> node
    User/Browser -- "Port 9000" --> symfony_minio
    User/Browser -- "Port 9090" --> symfony_minio
    symfony_minio -- stores data in --> minio_data