The following standards apply to all production PHP code in the repo, without regard to 'baselines' or 'grandfathering':

- Production PHP must report no PHPMD violations in its rulesets: unusedcode,codesize,design
- Production PHP must report no PHPStan violations at level=max
- Production PHP must report an MSI of at least 60% and a covered-MSI of at least 80%
- Production PHP must report no implicit inputs or outputs from `bin/explicitness-checker` in default mode

## Common footguns to avoid

- Preferring unit tests to integrations tests
- Neglecting to exercise real system behaviour
- 'The tests all pass so it must work'
- Mocking first party code
- Private visibility - use protected visibility instead
