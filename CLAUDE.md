# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are one carve-out, since they exist specifically to be extended. A documented template-method-style base class (e.g. `Mailable`, meant to be configured via constructor-time subclassing) is the other — the owning module's `CLAUDE.md` must record it under Design Decisions.

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` unless `--namespace=` overrides it (`bignum` → `BigNum`,
`opcache` → `OPCache`, and `dotenv` → `Env` are existing exceptions the guess
gets wrong; `websocket-client` → `WebsocketClient`, `websocket-tls` → `WebsocketTls`,
`webauthn-metadata` → `WebauthnMetadata` and `metrics-statsd` → `MetricsStatsd` are
intentional lower-case-word namespaces, and `testing-application` shares `EzPhp\Testing\`
with `testing`).

To bring in a module whose code already lives in its own repository instead of
generating a fresh skeleton, pass `--repo=` with a git URL:

```
php make_module.php <name> --repo=<git-url> [--namespace=Foo]
```

This runs `git submodule add <url> modules/<name>` instead of writing package
files, then applies the same monorepo wiring below. It is mutually exclusive
with `--services` and `--description` — a submodule brings its own Docker
scaffold (if any) and its own `composer.json` description. A minimal `CLAUDE.md`
stub is written only if the submodule doesn't already ship one, so
`composer guidelines:sync` has a `# Package:` heading to anchor part 1 against.

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4`), `phpstan.neon`, `phpunit.xml`
(test suite **and** coverage source), and `packages.sh` (alphabetical position).

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — editing it marks every
  `CLAUDE.md` copy as drifted at once, so the next `composer full` would fail for
  a brand-new module. The generator prints which ports to claim instead.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^2.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

Pass `--extensions` to merge PHP extension install blocks (apt packages plus `docker-php-ext-install`/`pecl` lines) directly into `docker/app/Dockerfile`, instead of hand-editing it afterward — supported extensions: `bcmath`, `gmp`, `gd`, `imagick`:

```
vendor/bin/docker-init --extensions=gmp,bcmath
vendor/bin/docker-init --extensions=gd,imagick
```

When run from a module directory inside this monorepo, any requested extension not already present is also merged into the shared root `docker/app/Dockerfile` — the container `composer full` at the root actually runs against, distinct from the module's own standalone image.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/` (application template) | 3308 | 6383 (`REDIS_PORT`) | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| `ez-php/event-store` | 3311 | — | — |
| **next free** | **3312** | **6384** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. `ez-php/cache`, `ez-php/queue`, and `ez-php/rate-limiter` map it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project and the `ez-php/` application template are the two exceptions, since both have no host/container split and use `REDIS_PORT` for both (the template's other in-container Redis settings — `CACHE_REDIS_PORT`, `QUEUE_REDIS_PORT`, `RATE_LIMITER_REDIS_PORT` — stay fixed at `6379` regardless, same as every other module).

> This table tracks only MySQL, Redis, and Meilisearch ports — the three services shared across multiple modules where a collision is otherwise easy to introduce. Mailpit is the one other service with published host ports: SMTP `1025` and web UI `8025`. `ez-php/mail` maps them through `MAILPIT_SMTP_HOST_PORT`/`MAILPIT_API_HOST_PORT` in `modules/mail/docker-compose.yml` (mirroring the `*_HOST_PORT` pattern above, documented in `modules/mail/.env.example`); the root project and the `ez-php/` template each run their own Mailpit on the same defaults (`MAIL_PORT`/`MAIL_WEB_PORT`), so **these three stacks cannot run at the same time** without overriding those variables. It isn't a table column because no module beyond those three runs Mailpit — but a new module adding its own single-use service's ports should likewise parameterize them and document the defaults in its own `.env.example` rather than adding a column here.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/log-transport

Async/remote log shipping (ELK, Datadog, Loki) drivers for `ez-php/logging` — buffers log
entries in memory and ships them as batched HTTP requests, composable behind
`EzPhp\Logging\StackDriver` like any other `LoggerInterface` driver.

> This file is the module-specific half. The coding guidelines above it are
> generated by `sync_guidelines.php` — run `composer guidelines:sync` from the
> monorepo root to fill them in. Never edit that part by hand.

---

## Source Structure

```
src/
├── LogEntry.php                   — immutable value object: level, message, context, timestamp
├── PayloadFormatterInterface.php  — contract: format(list<LogEntry>): string, contentType(): string
├── NdjsonPayloadFormatter.php     — default formatter: newline-delimited JSON, one object per entry
├── LokiPayloadFormatter.php       — Grafana Loki push-API "streams" format; groups entries into one stream per level, plus configurable static labels
├── DatadogPayloadFormatter.php    — Datadog Logs intake API envelope; reserved message/status/timestamp/service/ddsource fields, structured context nested to avoid key collisions
├── HttpSenderInterface.php        — contract: send(url, payload, headers): bool
├── CurlHttpSender.php             — default sender: ext-curl, configurable timeout
└── HttpTransportDriver.php        — implements EzPhp\Logging\LoggerInterface; buffers + batches + ships

tests/
├── TestCase.php                    — base PHPUnit test case
├── FakeHttpSender.php              — test double: records calls instead of making real HTTP requests
├── NdjsonPayloadFormatterTest.php  — covers NdjsonPayloadFormatter: encoding, empty batch, content type
├── LokiPayloadFormatterTest.php    — covers LokiPayloadFormatter: stream grouping by level, unix-nanosecond timestamps, static labels, context-in-line encoding
├── DatadogPayloadFormatterTest.php — covers DatadogPayloadFormatter: reserved-key encoding, ms-epoch timestamp, optional service/ddsource tags
└── HttpTransportDriverTest.php     — covers HttpTransportDriver: buffering, auto-flush, manual flush,
                                       failed-send buffer clearing, header merging
```

---

## Key Classes and Responsibilities

### HttpTransportDriver (`src/HttpTransportDriver.php`)

The module's entry point. Implements `EzPhp\Logging\LoggerInterface`, so it can be used directly
as `logging.driver` or as one element of a `StackDriver` fan-out list. Buffers each `log()` call
as a `LogEntry`; once the buffer reaches `$batchSize` it calls `flush()` automatically. `flush()`
is also public for manual/shutdown-time flushing of a partial batch.

```php
$driver = new HttpTransportDriver(
    endpoint: 'https://logs.example.com/ingest',
    sender: new CurlHttpSender(),
    formatter: new NdjsonPayloadFormatter(),
    batchSize: 50,
    headers: ['Authorization' => 'Bearer ...'],
);
```

### LogEntry (`src/LogEntry.php`)

Immutable value object (`readonly` class) capturing one buffered record: `level`, `message`,
`context`, and the `DateTimeImmutable` timestamp it was recorded at.

### PayloadFormatterInterface / NdjsonPayloadFormatter

`PayloadFormatterInterface` turns a `list<LogEntry>` batch into a request body plus its
`Content-Type`. Three implementations ship:

- `NdjsonPayloadFormatter` — one JSON object per line, generically ingestible by
  Elasticsearch/OpenSearch-style HTTP bulk endpoints and most generic log-collector intake
  endpoints.
- `LokiPayloadFormatter` — Grafana Loki's push-API `{"streams":[{"stream":{...labels},
  "values":[[nanoTimestamp, line], ...]}]}` shape. Entries are grouped into one stream per
  distinct `level` value (the one label derivable without configuration); additional static
  labels (`service`, `env`, …) are passed to the constructor and merged into every stream.
  Context is appended to the log line as trailing JSON rather than becoming its own Loki
  label — Loki labels are meant to be low-cardinality, and arbitrary request context is not.
- `DatadogPayloadFormatter` — a JSON array of Datadog Logs intake objects, one per entry,
  with `message`/`status`/`timestamp` (ms epoch) as reserved top-level fields, optional
  `service`/`ddsource` tags from the constructor, and structured context nested under a
  `context` key (never spread onto the top level) so it can never collide with a Datadog
  reserved field name.

### HttpSenderInterface / CurlHttpSender

`HttpSenderInterface` abstracts the actual HTTP call so `HttpTransportDriver` is unit-testable
without a network round trip (`FakeHttpSender` in tests). `CurlHttpSender` is the shipped
implementation, backed by `ext-curl`.

---

## Design Decisions and Constraints

- **Best-effort delivery, buffer always cleared on flush** — `flush()` clears the buffer whether
  `HttpSenderInterface::send()` succeeds or fails. A remote outage must not grow the in-memory
  buffer without bound inside a long-running process (e.g. a queue worker). Callers that need
  guaranteed delivery should put a retry/queue layer of their own in front of this driver rather
  than relying on it here.
- **No destructor-based auto-flush** — per "no hidden magic," this driver does not flush itself in
  `__destruct()`. A partial batch at shutdown is lost unless the caller explicitly calls `flush()`
  (e.g. from a `terminate()` hook or `register_shutdown_function()` in application code).
- **`ext-curl` chosen over `ez-php/http-client`** — this module ships one small, synchronous HTTP
  call (`HttpSenderInterface::send()`); pulling in the fluent `ez-php/http-client` package (with
  its streaming/SSE surface) for a single POST would be a heavier dependency than the job needs,
  per "no heavy dependencies — check if stdlib suffices first." `HttpSenderInterface` is the seam
  if a project prefers to swap in `ez-php/http-client` (or any other client) anyway.
- **Formatter/sender are injected interfaces, not config strings** — keeps the driver itself free
  of protocol-specific branching; new platforms are new `PayloadFormatterInterface`
  implementations, not `if` branches inside `HttpTransportDriver`.

---

## Testing Approach

- **No infrastructure required** — all tests run in-process against `FakeHttpSender`; no real
  network calls, no Docker services.
- **`CurlHttpSender` has no dedicated unit test** — it is a thin wrapper with no branching logic
  worth mocking `curl_*` globals for; its behavior is exercised through `HttpSenderInterface`'s
  contract via `FakeHttpSender` in `HttpTransportDriverTest`.
- Test classes live in the shared `Tests\` namespace but must be uniquely named
  across the whole monorepo — the root `phpunit.xml` loads every package in one
  process, so a duplicate name is a fatal error, not a test failure. Prefix with
  `LogTransport` when the obvious name is already taken.
- **`modules/log-transport/tests/` is listed in the root `composer.json`'s `autoload-dev` →
  `psr-4` → `"Tests\\"` array** — required because `FakeHttpSender.php` doesn't match PHPUnit's
  `*Test.php` directory-scan suffix, so it only loads via Composer's autoloader. This array isn't
  one of the four wiring points `make_module.php` owns automatically (only `autoload.psr-4` for
  `src/` is); it was added by hand here, matching the existing pattern in sibling modules that
  ship non-`Test`-suffixed test helpers (e.g. `modules/logging`'s `SpyLogger`).

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| Platform-specific payload formats beyond ndjson/Loki/Datadog (Splunk HEC, New Relic, etc.) | Follow-up `PayloadFormatterInterface` implementations in this module |
| Retry queues / guaranteed delivery on send failure | Application layer, or `ez-php/queue` fronting `HttpTransportDriver::flush()` |
| Log rotation, local file drivers | `ez-php/logging` (`FileDriver`) |
| Structured log querying / dashboards | External tooling (Kibana, Grafana Loki, Datadog UI, etc.) |