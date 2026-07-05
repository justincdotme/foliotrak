# ADR-0024: Reach the sensor gateway over TLS with certificate verification disabled

## Status
Accepted

## Date
2026-07-04

## Deciders
- Justin Christenson (developer and owner), who resolved this in the sensor integration epic's clarifications.

---

## Context

The sensor gateway serves HTTPS on an unprivileged port with a self-signed leaf certificate that rotates every week or two; certificate generation is the gateway's own concern and out of Foliotrak's scope. Every authenticated request carries the gateway API key in a header, so the transport must be encrypted: plain HTTP would put the key and readings on the LAN in cleartext.

A publicly-trusted certificate is impossible for a private LAN hostname (no public DNS, no ACME reachability), and the household has no local certificate authority. Foliotrak's own serving stack faced the same landscape and chose a self-signed leaf (ADR-0010).

---

## Decision

The HTTP client connects with certificate verification disabled: `SENSOR_TLS_VERIFY` defaults to `false` and is passed straight through as the client's `verify` option. The connection is encrypted but the gateway is not authenticated. The leaf is deliberately not pinned, because it rotates. To authenticate the gateway later, sign its leaves with a stable local CA and point `SENSOR_TLS_VERIFY` at that CA bundle; the config knob already accepts a path.

---

## Alternatives Considered

### Option A: Plain HTTP
Drop TLS entirely since both devices share a LAN.

**Rejected because**: the API key and readings would cross the network in cleartext, and any future move of the gateway (different VLAN, WiFi segment) would inherit that exposure silently.

### Option B: Pin the gateway's leaf certificate
Trust exactly the current certificate.

**Rejected because**: the leaf rotates every week or two by the gateway's own policy, so pinning means scheduled breakage and a manual re-pin chore that would train the operator to ignore TLS errors.

### Option C: Stand up a local CA now and verify against it
Sign the gateway's leaves with a household CA and enable verification.

**Rejected because**: it is the correct end state but requires CA infrastructure (key custody, signing on rotation, distribution) that does not exist yet. The decision keeps that path open rather than blocking on it.

---

## Pros

- The API key and readings are encrypted in transit today, with zero certificate-management coupling between the two systems.
- Weekly certificate rotation on the gateway is invisible to Foliotrak; nothing breaks, nothing needs re-pinning.
- The upgrade path to real authentication is a config value change once a CA exists, not a code change.

---

## Cons

- The gateway is not authenticated: a device on the LAN that can spoof the gateway's address could capture the API key or feed false readings. Trust is anchored entirely on LAN membership.
- `verify => false` is a pattern that must not leak to any client that leaves the LAN; it is safe here only because of the deployment shape.

---

## Consequences

### Positive
- Sensor integration ships without waiting on certificate infrastructure.

### Negative
- The security of the sensor pipeline is only as good as the LAN's; a hostile device on the network is inside the trust boundary.

### New Decisions Required
- If the gateway ever becomes reachable beyond the trusted LAN, or a household CA materializes for other reasons, gateway authentication needs its own decision (CA-signed leaves plus `SENSOR_TLS_VERIFY` pointed at the bundle).

---

## Influences

- ADR-0010 established the same LAN-first posture on Foliotrak's serving side: self-signed, encrypted, trust accepted by the operator.
- The single-household deployment model (ADR-0004): there is no third party whose data transits this link.

---

## Related Decisions

- [ADR-0010: Serve over self-signed TLS via nginx and bootstrap the stack with init.sh](./ADR-0010-self-signed-tls-via-nginx-and-init-script.md): the mirror-image decision for inbound TLS.
- [ADR-0023: Pull sensor readings through a SensorReadingSource port with a gateway HTTP adapter](./ADR-0023-sensor-source-port-with-gateway-adapter.md): the client this transport setting configures.

---

## Review Date

Condition-based: revisit if the gateway is exposed outside the trusted LAN, if a local CA is stood up for any reason, or if the gateway's certificate strategy changes.
