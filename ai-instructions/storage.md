# Storage — opaque object persistence

Platform contract for binary object storage behind `core()->storage`.

## Placement

| Layer | Responsibility |
|-------|----------------|
| **CORE_PHP** | `StorageInterface`, shared key validation, `FileStorage` |
| **Consuming app** | Register the backend class in INI `[services]`; future cloud adapters stay swappable |

Do not expose filesystem paths, bucket names, or provider URLs to callers. Application code uses **storage keys** only.

## Service registration

Register the backend class in INI — the class choice selects the medium:

```ini
[services]
storage = Core\Storage\FileStorage
```

To move to object storage later, point the same key at another implementation (for example `Core\Storage\S3Storage`) without changing callers:

```ini
[services]
storage = Core\Storage\S3Storage
```

There is **no `[storage] driver`** switch: swapping the registered service class is the supported extension point.

## Configuration

```ini
[storage]
root = "PHP/CACHE/storage"
```

For `FileStorage`, `root` is the on-disk directory that backs all keys. Use environment overlays for disposable paths in test (`artifacts/verify/storage`).

Future backends may ignore `root` and read provider-specific keys (`bucket`, `region`, …) from the same `[storage]` section without changing the PHP API.

## Access

```php
core()->storage->put('journeys/42/cover.bin', $bytes);
$bytes = core()->storage->read('journeys/42/cover.bin');
$stream = core()->storage->openReadStream('journeys/42/cover.bin');
fclose($stream);

if (core()->storage->exists('journeys/42/cover.bin')) {
    core()->storage->delete('journeys/42/cover.bin');
}
```

## Storage keys

Keys are opaque, relative, slash-separated identifiers:

- valid: `journeys/42/cover.bin`, `tmp/export.csv`
- invalid: absolute paths, empty strings, `.`, `..`, or segments containing parent references

Implementations must reject invalid keys before touching the backend.

## Contract (`StorageInterface`)

| Method | Semantics |
|--------|-----------|
| `put($key, $contents)` | Create or replace an object |
| `read($key)` | Return full contents; throw `StorageException` when missing |
| `openReadStream($key)` | Read-only stream for large payloads; caller closes the resource |
| `delete($key)` | Remove when present; return `false` when already absent |
| `exists($key)` | Whether an object is stored for the key |

Out of scope for this contract: public URLs, MIME detection, image transforms, multipart upload helpers. Add those in app-layer services that **use** storage, not inside the storage primitive.

## Implementing a new backend

1. Implement `StorageInterface` (extend `AbstractStorage` to reuse key validation).
2. Read backend settings from `core()->getConfigSection('storage')` in the constructor.
3. Map normalized keys to the provider object id without leaking provider details through the interface.
4. Register the class under `[services] storage`.
5. Keep parity tests against the same key scenarios (`put`, `read`, stream, `delete`, `exists`, invalid keys).

## Tests

- CORE_PHP: `php CORE_PHP/tests/run-storage-tests.php`
- MyJourney integration: `php tests/bin/test-storage.php`

Both are invoked by `./verify` in the MyJourney workspace.
