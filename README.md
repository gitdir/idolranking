# IdolRanking

Laravel 13-based idol and group rankings with member accounts, Heart voting and rewards, fan posts, profiles, and administrator tools. The local development target is Windows 11/XAMPP with MySQL 8.4; production is a split-path Hostinger Laravel deployment with MariaDB 11.8.9.

## Start here

- Codex instructions and mandatory fresh-session entry point: [`AGENTS.md`](AGENTS.md)
- Architecture, scope, decisions, and project progress: [`docs/PROJECT_OVERVIEW.md`](docs/PROJECT_OVERVIEW.md)
- New-PC setup, verification, credentials checklist, and unresolved portability issues: [`docs/PROJECT_RECOVERY.md`](docs/PROJECT_RECOVERY.md)
- SQL baseline and patch rules: [`sql/README.md`](sql/README.md)
- Deployment modes and exact commands: [`scripts/deploy/README.md`](scripts/deploy/README.md)

Never commit `.env`, credentials, private keys, database dumps, local uploads, `vendor`, `node_modules`, or generated build/cache files. Use `.env.example` as a template and keep real values in local or hosting-managed secret storage.

The current repository release commit is `86b685ec52c309e8a46dad2b4aa65a7e4993d553` on `main`. Repository state alone does not prove that this release was deployed to Hostinger; verify production read-only before reporting deployment status.
