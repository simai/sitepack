# Security Policy

## Reporting a vulnerability
If you believe you have found a security issue, please open a private security advisory on GitHub if available. If that is not possible, open a regular issue with the "security" label and avoid posting sensitive details.

We will acknowledge the report and work on a fix as quickly as possible.

## Notes for implementers
- Always enforce safe path handling when reading artifacts (no absolute paths, no traversal, no null bytes).
- Treat archives as untrusted input.
- Do not execute code bundles or apply secret configs automatically.
