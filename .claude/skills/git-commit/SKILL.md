---
name: git-commit
description: >
  Stages uncommitted files and creates a well-structured git commit message
  following Conventional Commits. NEVER pushes to remote. Use this skill
  whenever the user asks to commit, save changes, create a commit, write a
  commit message, stage files, or says things like "commit this", "save my
  work", "commit what I changed", "stage and commit", or "prepare a commit".
  Also trigger when the user finishes a task and wants to commit the result.
  This skill never pushes — it only stages and commits locally.
---

# Git Commit — Stage & Commit (No Push)

Stage uncommitted files and create a clear, well-structured commit message.
This skill NEVER pushes to a remote — it only works locally.

## Safety Rules

These rules are absolute and override everything else:

1. **NEVER run `git push`** — not even with flags, not even if asked. The skill
   stages and commits only. If the user asks to push, remind them that this
   skill is commit-only and they can push manually when ready.
2. **NEVER add a `Co-authored-by` trailer** — no co-author signatures of any
   kind in the commit message. Not for Claude, not for AI, not for anyone
   unless the user explicitly provides a human co-author name and email.
3. **NEVER add a `Signed-off-by` trailer** unless the project's git config or
   DCO workflow requires it and the user confirms.

## Workflow

### Step 1 — Inspect the working tree

Run these commands to understand what changed:

```bash
git status --short
git diff --stat
```

If there are no uncommitted changes, tell the user and stop.

### Step 2 — Detect issue number from branch name

Get the current branch name and extract an issue/ticket number if present:

```bash
git branch --show-current
```

Many branch naming conventions embed an issue number, for example:
`feature/58-add-login`, `fix/123-broken-cart`, `bugfix/42-null-pointer`,
`issue-99-refactor-auth`, `feat/7-onboarding`.

Extract the number from the branch name. Common patterns:

| Branch name | Extracted number |
|---|---|
| `feature/58-add-user-login` | `58` |
| `fix/123-broken-cart` | `123` |
| `bugfix/42-null-pointer` | `42` |
| `issue-99-refactor-auth` | `99` |
| `feat/7-onboarding-flow` | `7` |
| `hotfix/2055-memory-leak` | `2055` |

If a number is found, it becomes the **scope** of the commit message. For
example, branch `feature/58-add-login` produces `feat(58): add login page`.

If no number is found (e.g., `main`, `develop`, `feature/add-login`), fall back
to a descriptive scope in Step 5.

Keep the extracted number for use in Step 5.

### Step 3 — Review the changes

Read the actual diff to understand what was done:

```bash
git diff
git diff --cached
```

For untracked files, read their contents to understand what they add. This
context is essential for writing a good commit message — don't skip it.

### Step 4 — Stage files

Stage all uncommitted changes (modified, deleted, and new files):

```bash
git add -A
```

If only specific files should be committed (the user mentioned particular files
or the changes are clearly unrelated groups), stage selectively instead:

```bash
git add path/to/file1 path/to/file2
```

After staging, confirm what's staged:

```bash
git diff --cached --stat
```

### Step 5 — Write the commit message

Write a commit message following the Conventional Commits format:

```
<type>(<scope>): <short description>

<body>
```

**Scope from branch number** — if Step 2 extracted an issue number from the
branch name, use `<number>` as the scope. This takes priority over a
descriptive scope. Examples:

| Branch | Commit message |
|---|---|
| `feature/58-add-login` | `feat(58): add login page` |
| `fix/123-broken-cart` | `fix(123): prevent null pointer on empty cart` |
| `bugfix/42-api-timeout` | `fix(42): increase API timeout to 30s` |
| `feat/7-onboarding` | `feat(7): add welcome screen for new users` |

If no number was found in the branch, use a short descriptive scope instead
(e.g., `auth`, `api`, `models`). Omit the scope entirely if the change spans
the whole project.

**Type** — choose the one that best fits:

| Type | When to use |
|------|-------------|
| `feat` | New feature or functionality |
| `fix` | Bug fix |
| `refactor` | Code restructuring with no behavior change |
| `docs` | Documentation only |
| `style` | Formatting, whitespace, missing semicolons (no logic change) |
| `test` | Adding or updating tests |
| `chore` | Build, config, dependencies, tooling |
| `perf` | Performance improvement |
| `ci` | CI/CD pipeline changes |

**Short description** — imperative mood, lowercase, no period at the end. Max
72 characters. Describe *what* the commit does, not *how*.

Good: `feat(58): add JWT token refresh endpoint`
Bad: `feat(58): Added JWT token refresh endpoint.`

**Body** — optional but encouraged for non-trivial changes. Explain *why* the
change was made and any important context. Wrap lines at 72 characters.
Separate from the subject line with a blank line.

### Step 6 — Commit

```bash
git commit -m "<message>"
```

For multi-line messages with a body, use:

```bash
git commit -m "<subject>" -m "<body>"
```

### Step 7 — Confirm

After committing, show the result:

```bash
git log --oneline -1
```

Tell the user the commit was created successfully and remind them it has NOT
been pushed.

## Commit Message Examples

**Simple feature** (branch: `feature/58-add-cart-quantity`):
```
feat(58): add quantity selector to product page
```

**Bug fix with context** (branch: `fix/123-duplicate-charge`):
```
fix(123): prevent duplicate charge on retry

The payment gateway returns a 409 when a charge with the same
idempotency key already exists. Previously we retried without
checking, which could result in a double charge. Now we check
the response code and return the existing charge instead.
```

**Refactor** (branch: `feature/42-address-cleanup`):
```
refactor(42): extract address validation to value object

Address validation logic was duplicated across User, Order, and
Supplier models. Extracted to App\ValueObjects\Address with a
single validate() method.
```

**No issue number in branch** (branch: `develop`):
```
chore(deps): update laravel/framework to 11.x
```

**Docs, no number** (branch: `docs/api-examples`):
```
docs(api): add authentication examples to README
```

## Edge Cases

- **Mixed unrelated changes**: If `git status` shows changes that clearly
  belong in separate commits (e.g., a feature + an unrelated config fix),
  suggest splitting into multiple commits. Stage and commit each group
  separately.
- **Only untracked files**: These are new files. Stage them with `git add` and
  describe what they introduce in the commit message.
- **Only deletions**: Use `fix` or `refactor` type depending on why files were
  removed.
- **Merge conflicts present**: Do NOT commit. Tell the user they need to
  resolve conflicts first.
- **Dirty submodules**: Flag them and ask the user how to proceed.

## What This Skill Does NOT Do

- **Push** — never. The user pushes when they're ready.
- **Add co-author signatures** — no `Co-authored-by` trailers.
- **Amend previous commits** — unless the user explicitly asks.
- **Force anything** — no `--force`, no `--no-verify`.
- **Create branches** — commits on the current branch only.
