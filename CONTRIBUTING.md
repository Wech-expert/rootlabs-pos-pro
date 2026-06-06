# Contributing

Thank you for your interest in contributing to RootLabs POS for WooCommerce.

This project is an open source point of sale plugin for WooCommerce. Contributions are welcome, especially around stability, accessibility, documentation, testing, translations, and WooCommerce compatibility.

## Before opening a pull request

Please make sure your change:

- Does not include secrets, credentials, private URLs, database dumps, logs, or store data.
- Does not modify production business data unexpectedly.
- Preserves WooCommerce compatibility.
- Preserves HPOS compatibility.
- Keeps security checks such as nonces, capabilities, sanitization, and escaping.
- Does not commit node_modules, .env files, logs, backups, SQL dumps, or ZIP files.

## Development

Install dependencies:

    npm install

Run TypeScript checks:

    npm run typecheck

Build frontend assets:

    npm run build

Package a production ZIP:

    bash scripts/package-production.sh

## Coding principles

- Keep business operations auditable.
- Avoid destructive uninstall behavior.
- Prefer explicit validation over silent assumptions.
- Sanitize input and escape output.
- Do not expose tokens or operational diagnostics in public logs.
- Keep WordPress and WooCommerce conventions where practical.

## Security issues

Do not open public issues for security vulnerabilities. See SECURITY.md.
