# ez-php/log-transport

Async/remote log shipping (ELK, Datadog, Loki) drivers for `ez-php/logging`.

---

## Installation

```bash
composer require ez-php/log-transport
```

---

## Usage

```php
use EzPhp\LogTransport\CurlHttpSender;
use EzPhp\LogTransport\HttpTransportDriver;
use EzPhp\LogTransport\NdjsonPayloadFormatter;

$driver = new HttpTransportDriver(
    endpoint: 'https://logs.example.com/ingest',
    sender: new CurlHttpSender(),
    formatter: new NdjsonPayloadFormatter(),
    batchSize: 50,
    headers: ['Authorization' => 'Bearer ...'],
);

$driver->log('error', 'Something failed', ['user_id' => 42]);
// ... auto-flushes once batchSize entries are buffered; call $driver->flush() manually
// (e.g. at request shutdown) to ship a partial batch.
```

### Payload formats

| Formatter | Target | Shape |
|---|---|---|
| `NdjsonPayloadFormatter` (default) | Elasticsearch/OpenSearch bulk-style intake, generic HTTP collectors | One JSON object per line |
| `LokiPayloadFormatter` | Grafana Loki push API | `{"streams":[{"stream":{labels...},"values":[[nanoTs, line], ...]}]}`, grouped one stream per `level` |
| `DatadogPayloadFormatter` | Datadog Logs intake API | JSON array of `{"message","status","timestamp"(ms),"context":{...}}` objects |

```php
use EzPhp\LogTransport\LokiPayloadFormatter;

$driver = new HttpTransportDriver(
    endpoint: 'https://loki.example.com/loki/api/v1/push',
    sender: new CurlHttpSender(),
    formatter: new LokiPayloadFormatter(labels: ['service' => 'my-app', 'env' => 'production']),
);
```

```php
use EzPhp\LogTransport\DatadogPayloadFormatter;

$driver = new HttpTransportDriver(
    endpoint: 'https://http-intake.logs.datadoghq.com/api/v2/logs',
    sender: new CurlHttpSender(),
    formatter: new DatadogPayloadFormatter(service: 'my-app', source: 'php'),
    headers: ['DD-API-KEY' => getenv('DATADOG_API_KEY')],
);
```

---

## License

MIT
