### hlquery Integrations

Integrations connect external systems to hlquery by turning source records into searchable collection documents.

The common flow is:

1. Read records from the source system.
2. Normalize fields into stable document IDs, titles, searchable text, metadata, and timestamps.
3. Ensure the target collection exists with the expected schema.
4. Upsert documents through the hlquery HTTP API or a client library.
5. Record enough source metadata to make future syncs idempotent.
6. Re-run syncs on a schedule, webhook, queue event, or application workflow.

Integration code should keep source credentials outside version control, accept configuration from environment variables or local config files, and treat hlquery writes as repeatable operations. A sync should be safe to run more than once without creating duplicate documents.

Use the language-specific folders as implementation references. They show the same integration pattern in different runtimes: configure the source and hlquery client, transform source data, create or update collections, write documents, and optionally run a validation search.

