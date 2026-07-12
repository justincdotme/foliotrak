# ADR-0010: Serve over self-signed TLS via nginx and bootstrap the stack with init.sh

## Status
Accepted

## Date
2026-06-24

## Deciders
- Justin Christenson (developer and owner), who proposed and decided this during the Phase 0 foundation work.

---

## Context

Foliotrak is LAN-first and single-household. The SPA and API are served same-origin behind nginx, with Sanctum SPA cookie authentication (ADR-0005). Cookie auth is materially safer over HTTPS: `Secure` cookies are not sent in cleartext, and HSTS and the CSRF flow assume TLS. Serving the app over TLS even on the LAN keeps session cookies off the wire in plaintext and avoids a dev/prod split where one path is HTTP and the other HTTPS.

A publicly-trusted certificate is not obtainable for a private LAN hostname: there is no public DNS record and no inbound reachability for an ACME challenge. The install is operated by a single household, so a one-time browser trust prompt is acceptable.

Separately, standing the stack up by hand (generate a cert, write the env, install PHP dependencies, build frontend assets, migrate and seed, start compose) is undocumented and easy to get wrong. A repeatable one-command bring-up is needed, and the host deliberately carries no PHP or Node toolchain, so every step must run in a container.

---

## Decision

Terminate TLS at nginx with a self-signed certificate. The nginx start script selects the HTTPS server config when a certificate is present and falls back to HTTP otherwise; the HTTPS config redirects port 80 to 443.

A single idempotent `init.sh` is the entry point. It generates a self-signed certificate for an operator-supplied domain (default `localhost`, e.g. `foliotrak.lan` on the household resolver), pins the HTTPS-related environment (`APP_URL`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS`), builds the images, installs PHP dependencies into the bind-mounted `vendor/`, builds the frontend assets in a Node container, starts the stack with the base compose file, and runs the database migrations and seeders. The domain is a runtime parameter so the shared repository carries no household's private hostname.

---

## Alternatives Considered

### Option A: Plain HTTP on the LAN
Serve the app over HTTP and skip certificates entirely.

**Rejected because**: `Secure` session cookies and HSTS require TLS, serving Sanctum session cookies in cleartext on a shared LAN is a needless exposure, and an HTTP-only path diverges from any real deployment.

### Option B: A publicly-trusted CA certificate via ACME (Let's Encrypt)
Obtain an automatically-renewed certificate from a public CA.

**Rejected because**: a private LAN FQDN has no public DNS and no inbound reachability, so the ACME challenge cannot complete. Public certs are not possible for this deployment.

### Option C: A local CA toolchain (mkcert)
Run a local certificate authority and issue leaf certs trusted by importing the CA into each client.

**Rejected because**: it adds a dependency and a per-client trust-store install step. For a single-household install a self-signed leaf the operator accepts once is simpler, and trusting the generated cert remains available if wanted.

### Option D: Swap nginx for an auto-TLS proxy (Caddy)
Replace nginx with a proxy that manages certificates itself.

**Rejected because**: nginx is already the reverse proxy behind the same-origin model (ADR-0005); introducing a second proxy is unjustified for self-signed local TLS.

---

## Pros

- One command takes a clean checkout to a running, TLS-served, seeded stack with no PHP or Node toolchain on the host.
- TLS everywhere keeps Sanctum's `Secure` cookies and CSRF flow honest and matches how the app is actually reached.
- The cert-presence switch in nginx means the same image serves HTTP when no certificate exists, so the stack still boots without TLS configured.
- The served domain is a parameter, so the shared repo never hardcodes a household's private hostname.

---

## Cons

- A self-signed certificate triggers a browser trust warning until the operator imports or accepts it; there is no automated trust.
- The certificate is bound to one domain; changing the served hostname means regenerating it.
- `init.sh` rewrites the HTTPS-related `.env` keys on every run, so manual edits to those specific keys are overwritten by design.
- `SESSION_SECURE_COOKIE=true` requires reaching the app over HTTPS; the plain `http://localhost` path no longer authenticates (the Vite dev override stays available for HMR work).

---

## Consequences

### Positive
- Bringing the environment up is reproducible and documented as code, lowering the cost of fresh installs.
- Rotating the certificate is "delete `docker/nginx/certs` and re-run `init.sh`".

### Negative
- Operators do a one-time trust step (or click through) per client device.
- Plain HTTP at the FQDN now redirects to HTTPS, so any HTTP-only client must follow the redirect and accept the certificate.

### New Decisions Required
- If foliotrak is ever exposed beyond a trusted LAN, a publicly-trusted certificate and production hardening (sketched in the README) need their own decision.

---

## Influences

- ADR-0005 (same-origin SPA and API behind nginx, Sanctum cookie auth). TLS is what makes the cookie model safe in transit.
- ADR-0004 (auth as a gate) and the LAN-first, bind-mount conventions in CLAUDE.md.
- The llmao project's self-signed-cert plus cert-presence nginx switch, which this mirrors and extends to DNS-name certificates and a fuller bring-up.

---

## Related Decisions

- [ADR-0005: Authenticate the SPA with Laravel Sanctum in cookie and session mode](./ADR-0005-sanctum-spa-cookie-auth.md): the auth model this protects in transit.
- Future ADR needed: certificate and exposure strategy if the deployment model moves beyond a trusted LAN.

---

## Review Date

Condition-based: revisit if foliotrak is exposed outside a trusted LAN, or if a household needs the certificate trusted across many devices (then reconsider a local CA or a publicly-trusted certificate).
