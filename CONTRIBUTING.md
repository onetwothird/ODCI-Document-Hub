# Contributing to ODCI Document Hub

Thank you for your interest in contributing! This document provides guidelines for contributing to this project.

## Code of Conduct
By participating, you are expected to uphold our Code of Conduct. Please report unacceptable behavior to the maintainers.

## How to Contribute

### Reporting Bugs
1. Check if the issue already exists in [Issues](https://github.com/onetwothird/ODCI-Document-Hub/issues)
2. Use the **Bug Report** template
3. Provide clear reproduction steps, environment details, and expected vs actual behavior

### Suggesting Features
1. Check existing issues and discussions
2. Use the **Feature Request** template
3. Describe the problem, proposed solution, and use cases

### Improving Documentation
1. Use the **Documentation/Question** template
2. Be specific about what's missing, unclear, or incorrect
3. Suggest improved wording if possible

## Development Workflow

### Prerequisites
- PHP ≥ 8.1
- MariaDB/MySQL ≥ 10.4
- Composer ≥ 2.0

### Setup
```bash
# 1. Fork and clone
git clone https://github.com/YOUR-USERNAME/ODCI-Document-Hub.git
cd ODCI-Document-Hub

# 2. Install dependencies
composer install

# 3. Configure environment
cp .env.example .env
# Edit .env with your database and mail settings

# 4. Import database schema
mysql -u root -p < database/odci_db.sql

# 5. Set permissions (Linux/macOS)
chmod -R 775 uploads/ storage/logs/

# 6. Start development server
php -S localhost:8000 -t .
```

### Making Changes
1. Create a feature branch: `git checkout -b feat/your-feature-name`
2. Follow the [Conventional Commits](https://www.conventionalcommits.org/) format:
   - `feat:` new feature
   - `fix:` bug fix
   - `docs:` documentation changes
   - `chore:` maintenance, refactoring
   - `refactor:` code restructuring
   - `perf:` performance improvements
   - `security:` security fixes
3. Write clear, focused commits
4. Test your changes locally
5. Push to your fork and open a Pull Request

### Pull Request Guidelines
- Fill out the PR template completely
- Reference related issues: `Closes #123`
- Keep PRs focused and reasonably sized
- Ensure CI passes (if applicable)
- Request review from maintainers

### Coding Standards
- PSR-12 coding style for PHP
- Descriptive variable/function names
- Comments for complex logic
- No debug code in commits (`var_dump`, `console.log`, etc.)
- Follow existing patterns in the codebase

### Database Changes
- Document schema changes in PR description
- Provide migration SQL if applicable
- Consider backward compatibility

## Security
- **Never** commit `.env` files or credentials
- Report security vulnerabilities privately via [GitHub Security Advisories](https://github.com/onetwothird/ODCI-Document-Hub/security/advisories/new)
- Sanitize all user inputs
- Use parameterized queries

## License
By contributing, you agree that your contributions will be licensed under the same license as the project (proprietary for CvSU Naic Campus).

## Maintainers
- **Angelito P. Decatoria III** (Senior Software Engineer)
- **ITD Department** (System Administrators)

Questions? Open a [Discussion](https://github.com/onetwothird/ODCI-Document-Hub/discussions) or [Issue](https://github.com/onetwothird/ODCI-Document-Hub/issues/new/choose).