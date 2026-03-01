# Iteration Workflow

## Branch Model

- `main`: releasable baseline
- `feat/<topic>`: new features
- `fix/<topic>`: bug fixes
- `chore/<topic>`: maintenance

## Local Loop

1. Create branch:
   - `git checkout -b feat/<topic>`
2. Implement changes.
3. Run precheck:
   - `powershell -ExecutionPolicy Bypass -File .\scripts\precheck.ps1`
4. Commit with template:
   - `git config commit.template .gitmessage.txt`
   - `git commit`
5. Push branch:
   - `git push -u origin <branch>`

## Pull Request Checklist

- Precheck passed locally
- No credentials/secrets in changed files
- Docs updated when behavior changes
- Keep changes focused and small

## Release to Main

1. Merge PR to `main`
2. Run precheck again on `main`
3. Tag release if needed:
   - `git tag -a vX.Y.Z -m "release vX.Y.Z"`
   - `git push origin vX.Y.Z`
