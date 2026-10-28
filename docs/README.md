# Security design documentation

| # | Document | Assignment part |
|---|---|---|
| 1 | [System and use cases](01-system-and-use-cases.md) | application, users, use case diagram |
| 2 | [Threat analysis](02-threat-analysis.md) | attackers, attack trees, risk matrix, top risks |
| 3 | [Misuse cases and countermeasures](03-misuse-cases-and-countermeasures.md) | misuse case diagram, countermeasures |
| 4 | [Standard mapping](04-standard-mapping.md) | OWASP Proactive Controls coverage and improvements |
| 5 | [Secure coding and technical design](05-secure-coding-and-technical-design.md) | input validation + regexes, secrets, defaults, exceptions, standard, bonus |
| 6 | [Logging](06-logging.md) | concepts, levels, formats, examples, logging design |
| 7 | [Cryptography](07-cryptography.md) | password storage, encryption, OCSP/CRL, post-quantum TLS |
| 8 | [Security tests](08-security-tests.md) | misuse cases → test cases |
| 9 | [GitHub set-up](09-github-setup.md) | repository hardening from the in-depth research |

The flow for the presentation: **threats (2.2) → risks (2.4) → attacks (2.3) → misuse cases (3) →
countermeasures (3, 5) → standard (4) → tests (8)**.

## References

- OWASP Top 10 Proactive Controls 2024. https://top10proactive.owasp.org/
- OWASP Application Security Verification Standard 5.0. https://owasp.org/www-project-application-security-verification-standard/
- OWASP Cheat Sheet Series: Password Storage, Logging, Input Validation, File Upload, Secrets Management, Error Handling. https://cheatsheetseries.owasp.org/
- OWASP Secure Coding Practices Quick Reference Guide. https://owasp.org/www-project-secure-coding-practices-quick-reference-guide/
- NIST SP 800-63B Digital Identity Guidelines (authentication). https://pages.nist.gov/800-63-4/sp800-63b.html
- NIST SP 800-38D (GCM), SP 800-92 (log management), FIPS 197 (AES), FIPS 203 (ML-KEM).
- RFC 5280 (X.509, CRL), RFC 6960 (OCSP), RFC 5424 (syslog), RFC 9106 (Argon2).
- Laravel 13 documentation: Encryption, Hashing, Validation, Logging, Authorization. https://laravel.com/docs
