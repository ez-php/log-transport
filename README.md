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

Delivery is best-effort and never throws into your code: `flush()` clears the buffer whether the
send succeeds or not, and returns `false` on failure. Log data JSON cannot carry as is does not
fail the batch — every formatter encodes with `PayloadFormatterInterface::JSON_FLAGS`, which
turns invalid UTF-8 into U+FFFD and NAN/INF into `0`. A custom formatter that throws drops its
batch (`flush()` returns `false`) instead of keeping it buffered, and so does a sender that throws.

### Payload formats

| Formatter | Target | Shape |
|---|---|---|
| `NdjsonPayloadFormatter` (default) | Elasticsearch/OpenSearch bulk-style intake, generic HTTP collectors | One JSON object per line |
| `LokiPayloadFormatter` | Grafana Loki push API | `{"streams":[{"stream":{labels...},"values":[[nanoTs, line], ...]}]}`, grouped one stream per `level` |
| `DatadogPayloadFormatter` | Datadog Logs intake API | JSON array of `{"message","status","timestamp"(ms),"context":{...}}` objects |
| `SplunkHecPayloadFormatter` | Splunk HTTP Event Collector (`/services/collector/event`, header `Authorization: Splunk <token>`) | Concatenated `{"time","event":{"message","severity","context"}}` objects; optional `host`/`source`/`sourcetype`/`index` |
| `NewRelicPayloadFormatter` | New Relic Log API (`/log/v1`, header `Api-Key`) | `[{"common":{"attributes":{…}},"logs":[{"timestamp"(ms),"message","attributes":{"level","context"}}]}]` |
| `GelfPayloadFormatter` | Graylog GELF HTTP input (`/gelf`) | Newline-separated GELF 1.1 messages `{"version","host","short_message","timestamp","level"(syslog),"_<context key>"…}`; use `batchSize: 1` unless the input has bulk receiving enabled |
| `OtlpLogsPayloadFormatter` | OpenTelemetry collector OTLP/HTTP (`/v1/logs`) | `{"resourceLogs":[{resource{service.name}, scopeLogs[{logRecords[{timeUnixNano, severityNumber, severityText, body, attributes, traceId, spanId}]}]}]}`; `trace_id`/`span_id` from ez-php/otel's `TraceContextLogger` link the record to its span: `new OtlpLogsPayloadFormatter('billing')` |

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
