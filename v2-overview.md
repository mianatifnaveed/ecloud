```mermaid
classDiagram
    class vpc {
        char[12] id
        varchar[255] name
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    class region {
        +String id
        +String name
    }
    region --> "many" site : Contains

    class site {
        +String id
    }
    site -- "single" availability_zone : Has

    class availability_zone {
        char[12] id 
        varchar[255] code
        varchar[255] name
        uint site_id
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    availability_zone -- "single" gateway : Has

    class gateway {
        +String id
    }

    class network {
        +String id
    }
    network --> "single" router
    network --> "single" availability_zone

    class router {
        +String id
    }
    router --> "many" availability_zone : Has

    class vpn {
        +String id
    }
    vpn --> "many" router
    vpn --> "single" network
    vpn --> "single" availability_zone

    class instance {
        +String id
    }
    instance --> "many" network
    instance --> "single" storage : Has

    class storage {
        +String id
    }
```

# Notes

- The DHCP server needs to be added in somewhere, but we need to know what is required. What needs to be federated? What needs to be configered by the customer? etc..