# Domains

Domains consists of regroup classes by their business logic. This allows to have a better code organization. Each domain can have its own services, repositories, entities, controllers and so on.

```
src/
├── Domains/
│   ├── User/
│   │   ├── Controllers/
│   │   ├── Entities/
│   │   ├── Repositories/
│   │   ├── Services/
│   ├── Posts/
│   │   ├── Controllers/
│   │   ├── Entities/
│   │   ├── Repositories/
│   │   ├── Services/
│   └── ...
```

> TODO: It would be interesting to add a maker that generates a domain.