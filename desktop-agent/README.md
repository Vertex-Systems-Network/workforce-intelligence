# WorkIntel Desktop Agent — Reference Client

This is a small Node.js reference client for Milestone 5. It proves the enrollment, hashed device-token, heartbeat, remote-command and offline-batch-sync protocol. It intentionally does **not** capture applications, websites, screenshots, raw keyboard input, or clipboard content; those belong to later milestones.

Requires Node 20+.

## Enroll

Generate a one-time code from **Devices & Agents → Enroll Device**, then run:

```bash
set AGENT_SERVER_URL=https://your-workforce-server.example
node reference-agent.mjs enroll WI-XXXX-XXXX-XXXX
```

The server returns a device token once. The reference client stores it in `storage/device.json`; a production desktop agent must store the token in the operating system credential store instead of a plain JSON file.

## Run

```bash
node reference-agent.mjs run
```

The client sends a heartbeat, uploads queued events in idempotent batches and polls commands in the heartbeat response.

For a Laragon/private CA, trust the CA in Node using `NODE_EXTRA_CA_CERTS`. Do not turn off TLS certificate verification in production.


## Milestone 6 activity payload testing
The reference agent does not inspect foreground applications on the host OS. It can queue protocol-compatible sample sessions so the server ingestion path can be tested without native collectors:

```bash
node reference-agent.mjs record-app "Visual Studio Code" 120
node reference-agent.mjs record-domain github.com 120
node reference-agent.mjs run
```

Production foreground-window collection requires platform-specific signed/native components and is intentionally deferred to the production desktop-agent milestone. The server already rejects raw typed text, clipboard values and password content.
