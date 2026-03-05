# Security Policy

## Reporting a Vulnerability

Please do not open public issues for security problems.
Contact the maintainer privately and include:

- Affected script/file
- Reproduction steps
- Impact assessment
- Suggested mitigation

## Secret Handling Rules

- Never commit `.env` or real credentials
- Never hardcode passwords/API keys in code
- Use `.env.example` for placeholders
- Re-run secret scan before each release

## Pre-Release Security Checklist

1. `rg "REDACTED_PASSWORD_EXAMPLE|REDACTED_SERVER_IP_EXAMPLE|api_key=.*[A-Za-z0-9]"` returns no real secrets in tracked files.
2. `.env` is excluded by `.gitignore`.
3. No production backups, SQL dumps, or runtime uploads are tracked.
