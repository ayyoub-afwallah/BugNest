%%{init: {
  'theme': 'dark',
  'themeVariables': {
    'primaryColor': '#4CAF50',
    'primaryTextColor': '#ffffff',
    'primaryBorderColor': '#388E3C',
    'lineColor': '#FFC107',
    'secondaryColor': '#2196F3',
    'tertiaryColor': '#FF5722',
    'background': '#1a1a1a',
    'mainBkg': '#2d2d2d',
    'secondBkg': '#3d3d3d',
    'tertiaryBkg': '#4d4d4d'
  }
}}%%
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

    php -- depends on --> postgres
    User/Browser -- "Port 8080" --> nginx
    nginx -- depends on --> php
    postgres -- stores data in --> db_data
    User/Browser -- "Port 5050" --> pgadmin
    User/Browser -- "Port 3000" --> node
    User/Browser -- "Port 9000" --> minio
    User/Browser -- "Port 9090" --> minio
    minio -- stores data in --> minio_data

    %% Styling
    classDef userStyle fill:#2196F3,stroke:#1976D2,stroke-width:3px,color:#fff
    classDef serviceStyle fill:#4CAF50,stroke:#388E3C,stroke-width:2px,color:#fff
    classDef storageStyle fill:#FF9800,stroke:#F57C00,stroke-width:2px,color:#fff

    class User/Browser userStyle
    class php serviceStyle
    class nginx serviceStyle
    class postgres serviceStyle
    class pgadmin serviceStyle
    class node serviceStyle
    class minio serviceStyle
    class db_data storageStyle
    class minio_data storageStyle