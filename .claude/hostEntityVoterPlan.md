Host + Entity Level Permissions

```php
// packages/core/src/Entity/Role.php
class Role {
    public string $name;           // 'ROLE_HOST_EDITOR'
    public array $permissions;     // ['page.edit', 'media.upload']
    public ?array $allowedHosts;   // ['example.com'] or null for all
}
```

**Entity permissions:**

- `page.{view,create,edit,publish,delete}`
- `media.{view,upload,edit,delete}`
- `user.{view,create,edit,delete}`
- `settings.{view,edit}`

**Files to create:**

- `packages/core/src/Entity/Role.php`
- `packages/admin/src/Security/PageVoter.php`
- `packages/admin/src/Security/MediaVoter.php`
- `packages/admin/src/Security/HostAccessChecker.php`
