# Phase 24 Event and Template Context

Automation event patterns support exact values and wildcards, for example:

```text
payroll.paid
attendance.*
tasks.*
client_invoice.*
```

Common context:

```json
{
  "event": {
    "id": "event-uuid",
    "type": "payroll.paid",
    "occurred_at": "ISO-8601"
  },
  "payload": {},
  "workspace": {
    "id": 1,
    "name": "ACME Corporation",
    "slug": "acme-corp",
    "timezone": "UTC",
    "currency": "USD"
  },
  "run": {
    "id": 123,
    "uuid": "run-uuid"
  },
  "steps": {}
}
```

Templates use dot paths:

```text
{{event.type}}
{{payload.employee.name}}
{{payload.amount}}
{{workspace.name}}
{{steps.1.output.issue_key}}
```

If the whole string is one template expression, the original data type is retained. Embedded templates are stringified.
