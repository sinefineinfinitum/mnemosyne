---
type: psr4-entity
astHash: PLACEHOLDER
---

# `App\Service\UserService`

**Type**: class
**Parent**: `App\Abstracts\BaseService`
**Interfaces**: `App\Contracts\ServiceInterface`

## Methods

### `create`
```php
public static function create(string $name, array $data = []): App\Models\User
```

### `findById`
```php
public function findById(int $id, ?bool $active = true): ?App\Models\User
```

## Dependencies

- `App\Abstracts\BaseService`
- `App\Contracts\ServiceInterface`
- `App\Models\User`
